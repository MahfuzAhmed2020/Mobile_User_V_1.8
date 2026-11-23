package com.mobileuser;
import com.mobileuser.Domain.*;
import com.mobileuser.Repositories.*;
import com.mobileuser.Requests.*;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.junit.jupiter.api.*;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.boot.test.autoconfigure.web.servlet.AutoConfigureMockMvc;
import org.springframework.test.context.DynamicPropertySource;
import org.springframework.test.context.DynamicPropertyRegistry;
import org.springframework.test.context.bean.override.mockito.*;
import org.springframework.test.web.servlet.*;
import org.springframework.mock.web.MockHttpSession;
import org.springframework.mail.*;
import org.springframework.mail.javamail.JavaMailSender;
import org.springframework.mail.javamail.JavaMailSenderImpl;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.transaction.support.TransactionTemplate;
import org.springframework.transaction.PlatformTransactionManager;
import java.math.BigDecimal;
import java.time.Instant;
import java.nio.file.*;
import java.util.*;
import java.net.*;
import java.net.http.*;
import java.util.concurrent.*;
import static org.junit.jupiter.api.Assertions.*;
import static org.mockito.Mockito.*;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.*;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;
import static org.springframework.security.test.web.servlet.request.SecurityMockMvcRequestPostProcessors.csrf;

@SpringBootTest @AutoConfigureMockMvc
class StoreIntegrationTest {
 @DynamicPropertySource static void database(DynamicPropertyRegistry r) {
  String db=System.getenv("TEST_DB_NAME");
  if(db==null||!db.matches("mobile_user_springboot_test_[a-zA-Z0-9_]+"))throw new IllegalStateException("Set TEST_DB_NAME to an isolated mobile_user_springboot_test_* database.");
  r.add("spring.datasource.url",()->"jdbc:mysql://"+System.getenv().getOrDefault("DB_HOST","127.0.0.1")+":"+System.getenv().getOrDefault("DB_PORT","3306")+"/"+db+"?connectionTimeZone=UTC");
  r.add("app.url",()->"https://shop.example.invalid");r.add("app.mail.enabled",()->true);
 }
 @Autowired StoreService store;@Autowired Accounts users;@Autowired Products products;@Autowired Carts carts;@Autowired Addresses addresses;@Autowired Cards cards;@Autowired Purchases orders;@Autowired Trackings tracks;@Autowired PasswordEncoder encoder;@Autowired MockMvc mvc;@Autowired ObjectMapper json;@Autowired PlatformTransactionManager tm;
 @Autowired LegacyImporter importer;
 @MockitoBean JavaMailSender mail;@MockitoSpyBean Items items;
 static final String PASS="Synthetic1!";
 Long uid;String email;
 @BeforeEach void account(){email="integration-"+UUID.randomUUID()+"@example.invalid";uid=((Number)store.register(new Registration("Synthetic","Test",email,PASS,PASS)).get("user_id")).longValue();when(mail.createMimeMessage()).thenAnswer(invocation->new jakarta.mail.internet.MimeMessage((jakarta.mail.Session)null));clearInvocations(mail);}
 Checkout request(String key){return new Checkout(addresses.findByUserIdAndKindOrderByIdAsc(uid,"checkout").getFirst().id,"4111111111111111",new BigDecimal("49.99"),key);}
 Map<String,Object> buy(){store.changeCart(uid,1L,"POST",null,null);return store.checkout(uid,request(UUID.randomUUID().toString()));}
 MockHttpSession login(String accountEmail,String password) throws Exception {var result=mvc.perform(post("/api/auth/login").with(csrf()).contentType("application/json").content(json.writeValueAsString(Map.of("email",accountEmail,"password",password)))).andExpect(status().isOk()).andReturn();return (MockHttpSession)result.getRequest().getSession(false);}
 @Test void authenticationCsrfAndPostLogoutPersistData() throws Exception {
  mvc.perform(get("/api/cart")).andExpect(status().isUnauthorized());
  mvc.perform(post("/api/auth/login").contentType("application/json").content("{}")).andExpect(status().isForbidden());
  mvc.perform(post("/api/auth/login").with(csrf()).contentType("application/json").content(json.writeValueAsString(Map.of("email",email,"password","Wrong1!")))).andExpect(status().isUnauthorized());
  var session=login(email,PASS);
  mvc.perform(patch("/api/cart").session(session).with(csrf()).contentType("application/json").content("{\"product_id\":1,\"quantity\":1.5}")).andExpect(status().isUnprocessableEntity());
  store.changeCart(uid,1L,"POST",null,null);
  mvc.perform(get("/api/cart").session(session)).andExpect(jsonPath("$.item_count").value(1));
  mvc.perform(get("/api/auth/logout").session(session)).andExpect(status().is4xxClientError());
  mvc.perform(post("/api/auth/logout").session(session).with(csrf())).andExpect(status().isOk());assertTrue(session.isInvalid());
  assertEquals(1,store.cart(uid).get("item_count"));
  var again=login(email,PASS);mvc.perform(get("/api/cart").session(again)).andExpect(jsonPath("$.item_count").value(1));
 }
 @Test void phpBcryptHashesAuthenticateRatherThanJustMatch() throws Exception {
  var fixtures=json.readTree(Files.readString(Path.of("src/test/resources/php-bcrypt.json")));
  for(var fixture:fixtures){var account=users.findById(uid).orElseThrow();account.password=fixture.get("hash").asText();users.saveAndFlush(account);assertTrue(encoder.matches(fixture.get("password").asText(),account.password));assertFalse(encoder.matches("wrong",account.password));assertNotNull(login(email,fixture.get("password").asText()));}
 }
 @Test void cartUsesExactDecimalTotalsAndBelongsToAccount(){
  store.changeCart(uid,1L,"POST",null,null);store.changeCart(uid,1L,"PATCH",3,null);store.changeCart(uid,4L,"POST",null,null);
  var cart=store.cart(uid);assertEquals(new BigDecimal("162.96"),cart.get("total"));assertEquals(4,cart.get("item_count"));
  var rows=(List<?>)cart.get("data");assertEquals(new BigDecimal("149.97"),((Map<?,?>)rows.getFirst()).get("subtotal"));
  Long other=((Number)store.register(new Registration("Other","Synthetic","other-"+UUID.randomUUID()+"@example.invalid",PASS,PASS)).get("user_id")).longValue();assertEquals(0,store.cart(other).get("item_count"));
  store.changeCart(uid,1L,"PATCH",0,null);assertEquals(1,store.cart(uid).get("item_count"));store.changeCart(uid,4L,"DELETE",null,null);assertEquals(new BigDecimal("0.00"),store.cart(uid).get("total"));
 }
 @Test void checkoutSnapshotsAndIdempotency(){
  store.changeCart(uid,1L,"POST",null,null);var req=request(UUID.randomUUID().toString());var result=store.checkout(uid,req);Long oid=((Number)result.get("order_id")).longValue();
  assertEquals(0,store.cart(uid).get("item_count"));assertTrue(tracks.findByOrderId(oid).isPresent());assertEquals(new BigDecimal("49.99"),items.findByOrderIdOrderByIdAsc(oid).getFirst().price);
  var p=products.findById(1L).orElseThrow();var price=p.price;try{p.price=new BigDecimal("59.99");products.saveAndFlush(p);assertEquals(new BigDecimal("49.99"),items.findByOrderIdOrderByIdAsc(oid).getFirst().price);assertEquals(new BigDecimal("49.99"),store.myOrders(uid).getFirst().get("total"));}finally{p.price=price;products.saveAndFlush(p);}
  var retry=store.checkout(uid,req);assertEquals(oid,retry.get("order_id"));verify(mail,times(1)).send(any(jakarta.mail.internet.MimeMessage.class));
  var changed=new Checkout(req.address_id(),req.card_number(),new BigDecimal("1.00"),req.submission_key());assertEquals(409,assertThrows(ApiError.class,()->store.checkout(uid,changed)).status);
 }
 @Test void deliveryQuoteIncludesShippingAndProtectsCartAndOwnership(){
  store.changeCart(uid,1L,"POST",null,null);var base=request(UUID.randomUUID().toString());
  var quotes=store.deliveryRates(uid,base.address_id());var express=quotes.get(1);Long qid=((Number)express.get("id")).longValue();
  var req=new Checkout(base.address_id(),base.card_number(),new BigDecimal("61.99"),base.submission_key(),qid);
  var result=store.checkout(uid,req);Long oid=((Number)result.get("order_id")).longValue();
  assertEquals(new BigDecimal("61.99"),orders.findById(oid).orElseThrow().total);
  assertEquals(new BigDecimal("12.00"),orders.findById(oid).orElseThrow().shippingAmount);
  assertEquals(oid,store.checkout(uid,req).get("order_id"));
  assertEquals(false,store.myOrders(uid).getFirst().get("can_update_address"));
  store.changeCart(uid,1L,"POST",null,null);var stale=store.deliveryRates(uid,base.address_id()).getFirst();
  store.changeCart(uid,1L,"POST",null,null);
  assertEquals(409,assertThrows(ApiError.class,()->store.checkout(uid,new Checkout(base.address_id(),base.card_number(),new BigDecimal("99.98"),UUID.randomUUID().toString(),((Number)stale.get("id")).longValue()))).status);
  Long other=((Number)store.register(new Registration("Other","Quote","quote-"+UUID.randomUUID()+"@example.invalid",PASS,PASS)).get("user_id")).longValue();
  store.changeCart(other,1L,"POST",null,null);Long aid=addresses.findByUserIdAndKindOrderByIdAsc(other,"checkout").getFirst().id;
  assertEquals(422,assertThrows(ApiError.class,()->store.checkout(other,new Checkout(aid,base.card_number(),new BigDecimal("61.99"),UUID.randomUUID().toString(),qid))).status);
 }
 @Test void checkoutRejectsChangedReviewedTotalAndForeignSelections(){
  store.changeCart(uid,1L,"POST",null,null);var req=request(UUID.randomUUID().toString());long before=orders.count();
  assertEquals(409,assertThrows(ApiError.class,()->store.checkout(uid,new Checkout(req.address_id(),req.card_number(),new BigDecimal("0.01"),req.submission_key()))).status);
  Long other=((Number)store.register(new Registration("Foreign","Test","foreign-"+UUID.randomUUID()+"@example.invalid",PASS,PASS)).get("user_id")).longValue();
  Long aid=addresses.findByUserIdAndKindOrderByIdAsc(other,"checkout").getFirst().id;
  assertEquals(422,assertThrows(ApiError.class,()->store.checkout(uid,new Checkout(aid,req.card_number(),req.expected_total(),req.submission_key()))).status);
  assertEquals(422,assertThrows(ApiError.class,()->store.checkout(uid,new Checkout(req.address_id(),"1234",req.expected_total(),req.submission_key()))).status);
  assertEquals(before,orders.count());assertEquals(1,store.cart(uid).get("item_count"));
 }
 @Test void checkoutRollsBackOrderItemsTrackingAndCartOnFailure(){
  store.changeCart(uid,1L,"POST",null,null);long before=orders.count(),trackingBefore=tracks.count(),itemBefore=items.count();
  doThrow(new IllegalStateException("Injected item failure")).when(items).save(any(PurchaseItem.class));
  assertThrows(IllegalStateException.class,()->store.checkout(uid,request(UUID.randomUUID().toString())));
  assertEquals(before,orders.count());assertEquals(trackingBefore,tracks.count());assertEquals(itemBefore,items.count());assertEquals(1,store.cart(uid).get("item_count"));verifyNoInteractions(mail);
 }
 @Test void concurrentDuplicateCheckoutCreatesOneOrder() throws Exception {
  store.changeCart(uid,1L,"POST",null,null);var req=request(UUID.randomUUID().toString());
  try(var executor=Executors.newFixedThreadPool(2)){var start=new CountDownLatch(1);Callable<Object> task=()->{start.await();return store.checkout(uid,req).get("order_id");};var first=executor.submit(task);var second=executor.submit(task);start.countDown();assertEquals(first.get(20,TimeUnit.SECONDS),second.get(20,TimeUnit.SECONDS));}
  assertEquals(1,store.myOrders(uid).size());verify(mail,times(1)).send(any(jakarta.mail.internet.MimeMessage.class));
 }
 @Test void ordersTrackingAndAddressSelectionsEnforceOwnership(){
  var result=buy();Long oid=((Number)result.get("order_id")).longValue();String tracking=result.get("tracking_number").toString();
  Long other=((Number)store.register(new Registration("Other","Owner","owner-"+UUID.randomUUID()+"@example.invalid",PASS,PASS)).get("user_id")).longValue();
  assertTrue(store.myOrders(other).isEmpty());assertEquals(404,assertThrows(ApiError.class,()->store.tracking(other,tracking)).status);assertEquals(404,assertThrows(ApiError.class,()->store.cancel(other,oid)).status);
  Long foreign=addresses.findByUserIdAndKindOrderByIdAsc(other,"alternative").getFirst().id;
  assertEquals(422,assertThrows(ApiError.class,()->store.updateAddress(uid,new AddressChange(oid,foreign))).status);
 }
 @Test void addressChangePersistsIncludesPreviousNewAndLinkAndNoDuplicateEmail(){
  var result=buy();Long oid=((Number)result.get("order_id")).longValue();String before=orders.findById(oid).orElseThrow().deliveryAddress;
  var address=addresses.findByUserIdAndKindOrderByIdAsc(uid,"alternative").get(1);clearInvocations(mail);
  var saved=store.updateAddress(uid,new AddressChange(oid,address.id));assertEquals(true,saved.get("changed"));assertEquals(true,saved.get("email_sent"));assertEquals(address.id,orders.findById(oid).orElseThrow().whitelistAddressId);
  var captor=org.mockito.ArgumentCaptor.forClass(SimpleMailMessage.class);verify(mail).send(captor.capture());String body=captor.getValue().getText();assertTrue(body.contains(before));assertTrue(body.contains(address.formatted()));assertTrue(body.contains("https://shop.example.invalid/orders?tracking="));
  assertEquals(false,store.updateAddress(uid,new AddressChange(oid,address.id)).get("changed"));verify(mail,times(1)).send(any(SimpleMailMessage.class));
  var track=tracks.findByOrderId(oid).orElseThrow();track.status="Shipped";tracks.saveAndFlush(track);assertEquals(409,assertThrows(ApiError.class,()->store.updateAddress(uid,new AddressChange(oid,address.id))).status);
 }
 @Test void cancellationSoftDeletesTrackingAndOrder(){var result=buy();Long oid=((Number)result.get("order_id")).longValue();clearInvocations(mail);store.cancel(uid,oid);assertTrue(store.myOrders(uid).isEmpty());assertNotNull(orders.findById(oid).orElseThrow().deletedAt);assertNotNull(tracks.findByOrderId(oid).orElseThrow().deletedAt);assertEquals(404,assertThrows(ApiError.class,()->store.tracking(uid,result.get("tracking_number").toString())).status);verify(mail).send(any(SimpleMailMessage.class));}
 @Test void mailFailuresCannotUndoSavedChanges(){
  doThrow(new MailSendException("Synthetic SMTP outage")).when(mail).send(any(SimpleMailMessage.class));doThrow(new MailSendException("Synthetic SMTP outage")).when(mail).send(any(jakarta.mail.internet.MimeMessage.class));var result=buy();Long oid=((Number)result.get("order_id")).longValue();assertEquals(false,result.get("email_sent"));assertTrue(orders.existsById(oid));
  var address=addresses.findByUserIdAndKindOrderByIdAsc(uid,"alternative").get(1);assertEquals(false,store.updateAddress(uid,new AddressChange(oid,address.id)).get("email_sent"));assertEquals(address.id,orders.findById(oid).orElseThrow().whitelistAddressId);
  var registration=store.register(new Registration("Mail","Failure","mail-failure-"+UUID.randomUUID()+"@example.invalid",PASS,PASS));assertEquals(false,registration.get("email_sent"));assertTrue(users.existsById(((Number)registration.get("user_id")).longValue()));
 }
 @Test void resetTokensAreHashedExpireAndConsumeOnceAndOldPasswordFails() throws Exception {
  store.forgot(email);var captor=org.mockito.ArgumentCaptor.forClass(SimpleMailMessage.class);verify(mail).send(captor.capture());String token=captor.getValue().getText().split("token=")[1];var a=users.findById(uid).orElseThrow();assertEquals(64,token.length());assertNotEquals(token,a.resetTokenHash);assertEquals(StoreService.digest(token),a.resetTokenHash);assertTrue(a.resetExpires.isAfter(Instant.now().plusSeconds(3500)));
  assertEquals(422,assertThrows(ApiError.class,()->store.reset(new Reset("0".repeat(64),"NewValid1!","NewValid1!"))).status);
  assertEquals(422,assertThrows(ApiError.class,()->store.reset(new Reset(token,"weak","weak"))).status);
  assertEquals(422,assertThrows(ApiError.class,()->store.reset(new Reset(token,"NewValid1!","Other1!"))).status);
  a.resetExpires=Instant.now().minusSeconds(1);users.saveAndFlush(a);assertEquals(422,assertThrows(ApiError.class,()->store.reset(new Reset(token,"NewValid1!","NewValid1!"))).status);
  a.resetExpires=Instant.now().plusSeconds(3600);users.saveAndFlush(a);store.reset(new Reset(token,"NewValid1!","NewValid1!"));assertNull(users.findById(uid).orElseThrow().resetTokenHash);assertEquals(422,assertThrows(ApiError.class,()->store.reset(new Reset(token,"NewValid1!","NewValid1!"))).status);
  mvc.perform(post("/api/auth/login").with(csrf()).contentType("application/json").content(json.writeValueAsString(Map.of("email",email,"password",PASS)))).andExpect(status().isUnauthorized());assertNotNull(login(email,"NewValid1!"));
 }
 @Test void concurrentResetConsumesTokenAtomically() throws Exception {
  String token=StoreService.randomToken();new TransactionTemplate(tm).executeWithoutResult(tx->{var a=users.findById(uid).orElseThrow();a.resetTokenHash=StoreService.digest(token);a.resetExpires=Instant.now().plusSeconds(3600);users.saveAndFlush(a);});
  try(var executor=Executors.newFixedThreadPool(2)){var start=new CountDownLatch(1);Callable<Boolean> action=()->{start.await();try{store.reset(new Reset(token,"Concurrent1!","Concurrent1!"));return true;}catch(ApiError e){return false;}};var one=executor.submit(action);var two=executor.submit(action);start.countDown();assertNotEquals(one.get(20,TimeUnit.SECONDS),two.get(20,TimeUnit.SECONDS));}
 }
 @Test void profileValidationUniquenessAndCurrentPasswordAreEnforced() throws Exception {
  var session=login(email,PASS);mvc.perform(post("/api/account").session(session).with(csrf()).contentType("application/json").content("{\"first_name\":\" \",\"last_name\":\"Test\",\"email\":\"bad\"}")).andExpect(status().isUnprocessableEntity());
  assertEquals(409,assertThrows(ApiError.class,()->store.register(new Registration("Duplicate","Test",email,PASS,PASS))).status);
  assertEquals(422,assertThrows(ApiError.class,()->store.updateProfile(uid,new Profile("New","Name",email,"wrong","NewValid1!","NewValid1!"))).status);
  store.updateProfile(uid,new Profile("New","Name",email,PASS,"NewValid1!","NewValid1!"));assertTrue(encoder.matches("NewValid1!",users.findById(uid).orElseThrow().password));assertEquals("New",store.profile(uid).get("first_name"));
 }
 @Test void publicProductSharingHasNoAccountOrSessionInformation() throws Exception {
  var body=mvc.perform(get("/products/1")).andExpect(status().isOk()).andExpect(content().string(org.hamcrest.Matchers.containsString("Wireless Earbuds"))).andReturn().getResponse().getContentAsString();
  assertTrue(body.contains("https://shop.example.invalid/products/1"));assertFalse(body.contains(";jsessionid"));assertFalse(body.contains(email));assertTrue(body.contains("Sign in to add to cart"));
  mvc.perform(get("/api/products")).andExpect(status().isOk()).andExpect(jsonPath("$.data.length()").value(15));mvc.perform(get("/products/999999")).andExpect(status().isNotFound());mvc.perform(post("/api/cart").with(csrf()).contentType("application/json").content("{\"product_id\":1}")).andExpect(status().isUnauthorized());
  mvc.perform(get("/docs")).andExpect(status().is3xxRedirection());mvc.perform(get("/swagger-ui/index.html")).andExpect(status().isOk());mvc.perform(get("/openapi.json")).andExpect(status().isOk());
 }
 @Test void seedIsRepeatable(){int a=addresses.findByUserIdAndKindOrderByIdAsc(uid,"checkout").size(),c=cards.findByUserIdOrderByIdAsc(uid).size();store.seedAccount(uid);store.seedAccount(uid);assertEquals(a,addresses.findByUserIdAndKindOrderByIdAsc(uid,"checkout").size());assertEquals(c,cards.findByUserIdOrderByIdAsc(uid).size());}
 @Test void actualMailHogCapturesAllFiveNotificationTypes() throws Exception {
  var smtp=new JavaMailSenderImpl();smtp.setHost("127.0.0.1");smtp.setPort(1025);var props=new Properties();props.put("mail.smtp.connectiontimeout","2000");props.put("mail.smtp.timeout","2000");smtp.setJavaMailProperties(props);
  doAnswer(invocation->{smtp.send((SimpleMailMessage)invocation.getArgument(0));return null;}).when(mail).send(any(SimpleMailMessage.class));
  doAnswer(invocation->{smtp.send((jakarta.mail.internet.MimeMessage)invocation.getArgument(0));return null;}).when(mail).send(any(jakarta.mail.internet.MimeMessage.class));
  String recipient="mailhog-"+UUID.randomUUID()+"@example.invalid";Long id=((Number)store.register(new Registration("MailHog","Synthetic",recipient,PASS,PASS)).get("user_id")).longValue();
  store.changeCart(id,1L,"POST",null,null);Long aid=addresses.findByUserIdAndKindOrderByIdAsc(id,"checkout").getFirst().id;var bought=store.checkout(id,new Checkout(aid,"4111111111111111",new BigDecimal("49.99"),UUID.randomUUID().toString()));Long oid=((Number)bought.get("order_id")).longValue();
  store.updateAddress(id,new AddressChange(oid,addresses.findByUserIdAndKindOrderByIdAsc(id,"alternative").get(1).id));store.forgot(recipient);store.cancel(id,oid);
  var http=HttpClient.newHttpClient();var response=http.send(HttpRequest.newBuilder(URI.create("http://127.0.0.1:8025/api/v2/search?kind=to&query="+URLEncoder.encode(recipient,java.nio.charset.StandardCharsets.UTF_8))).GET().build(),HttpResponse.BodyHandlers.ofString());assertEquals(200,response.statusCode());var captures=json.readTree(response.body());assertEquals(5,captures.get("total").asInt());String all=captures.toString();for(String subject:List.of("Welcome to Mobile User","Your Mobile User order","Delivery address changed","Reset your Mobile User password","has been Cancelled"))assertTrue(all.contains(subject),subject);
 }
 com.fasterxml.jackson.databind.JsonNode legacyFixture(String key,String legacyEmail) throws Exception {
  String hash=json.readTree(Files.readString(Path.of("src/test/resources/php-bcrypt.json"))).get(0).get("hash").asText();
  return json.readTree("""
  {"format":1,"source_key":"%s","tables":{
   "users":[{"id":101,"first_name":"Legacy","last_name":"Synthetic","email":"%s","password":"%s","created_at":"2025-01-01T12:00:00Z"}],
   "products":[{"id":201,"name":"Wireless Earbuds","description":"Wireless earbuds for everyday listening.","price":"49.99"}],
   "addresses":[{"id":301,"user_id":101,"address_line":"Original Synthetic Street","city":"Demo","state":"TS","zip":"00000","country":"Test"}],
   "addresses_whitelist":[{"id":302,"user_id":101,"address_line":"Alternative Synthetic Street","city":"Demo","state":"TS","zip":"00000","country":"Test"}],
   "cart":[{"id":401,"user_id":101,"product_id":201,"quantity":2}],
   "orders":[{"id":501,"user_id":101,"address_id":301,"whitelist_address_id":302,"total":"1.23","deleted_at":null,"created_at":"2025-02-02T12:00:00Z"}],
   "order_items":[{"id":601,"order_id":501,"product_id":201,"product_name":"Historical Earbuds","price":"1.23","quantity":1}],
   "order_tracking":[{"id":701,"order_id":501,"tracking_number":"%s","status":"Processing","deleted_at":null}]
  }}
  """.formatted(key,legacyEmail,hash,UUID.randomUUID().toString().replace("-","")));
 }
 @Test void legacyImportRemapsIdsPreservesHashesSnapshotsAndRepeatsWithoutOverwriting() throws Exception {
  String importedEmail="legacy-"+UUID.randomUUID()+"@example.invalid";var fixture=legacyFixture("fixture-"+UUID.randomUUID(),importedEmail);
  long before=orders.count();assertEquals(1,importer.importData(fixture).get("new_accounts"));var account=users.findByEmail(importedEmail).orElseThrow();assertNotEquals(101L,account.id);
  var imported=store.myOrders(account.id).getFirst();assertEquals(new BigDecimal("1.23"),imported.get("total"));assertTrue(imported.get("delivery_address").toString().contains("Alternative Synthetic Street"));assertEquals("Historical Earbuds",((Map<?,?>)((List<?>)imported.get("items")).getFirst()).get("name"));assertEquals(2,store.cart(account.id).get("item_count"));assertNotNull(login(importedEmail,"Synthetic1!"));
  assertEquals(0,importer.importData(fixture).get("new_accounts"));assertEquals(before+1,orders.count());verifyNoInteractions(mail);
 }
 @Test void legacyImportConflictsRollbackRatherThanOverwrite() throws Exception {
  var fixture=legacyFixture("conflict-"+UUID.randomUUID(),email);long before=users.count();assertThrows(IllegalArgumentException.class,()->importer.importData(fixture));assertEquals(before,users.count());assertEquals("Synthetic",users.findById(uid).orElseThrow().firstName);
  var incomplete=(com.fasterxml.jackson.databind.node.ObjectNode)legacyFixture("incomplete-"+UUID.randomUUID(),"incomplete-"+UUID.randomUUID()+"@example.invalid");((com.fasterxml.jackson.databind.node.ArrayNode)incomplete.get("tables").get("order_tracking")).removeAll();assertThrows(IllegalArgumentException.class,()->importer.importData(incomplete));assertEquals(before,users.count());
 }

}
