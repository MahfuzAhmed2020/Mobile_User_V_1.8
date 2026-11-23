package com.mobileuser;

import com.mobileuser.Domain.*;
import com.mobileuser.Repositories.*;
import com.mobileuser.Requests.*;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;
import org.springframework.context.ApplicationEventPublisher;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.beans.factory.annotation.Value;
import java.math.BigDecimal;
import java.time.Instant;
import java.util.*;
import java.security.*;
import java.nio.charset.StandardCharsets;
@Service @Transactional
public class StoreService {
 final Accounts users; final Products products; final Carts carts; final Addresses addresses; final Cards cards;
 final Purchases orders; final Items items; final Trackings tracks; final PasswordEncoder encoder; final ApplicationEventPublisher events;
 final String appUrl;
 @org.springframework.beans.factory.annotation.Autowired Shipping shipping;
 @org.springframework.beans.factory.annotation.Autowired DeliveryQuotes deliveryQuotes;
 String cartFingerprint(Long uid){return digest(carts.findByUserIdOrderByIdAsc(uid).stream().map(c->c.productId+":"+c.quantity+":"+product(c.productId).price.setScale(2)).collect(java.util.stream.Collectors.joining("|")));}
 public List<Map<String,Object>> deliveryRates(Long uid,Long aid){
  var user=lock(uid);var address=ownedAddress(uid,aid,"checkout");var cart=cart(uid);
  int count=(int)cart.get("item_count");if(count==0)throw new ApiError(409,"Your cart is empty.");
  return shipping.rates(uid,address,user,cartFingerprint(uid),count).stream().map(q->{deliveryQuotes.save(q);return Map.<String,Object>of("id",q.id,"carrier",q.carrier,"service",q.service,"amount",q.amount,"currency","USD","estimated_days",q.days,"expires_at",q.expiresAt.toString(),"demo",!shipping.enabled());}).toList();
 }
 public Map<String,Object> createTestLabel(Long uid,Long oid){
  lock(uid);var o=ownedOrder(uid,oid);var t=tracks.findByOrderId(oid).orElseThrow();
  if(t.labelUrl!=null)return orderView(o);
  if(o.deliveryQuoteId==null)throw new ApiError(409,"This order has no carrier rate.");
  var q=deliveryQuotes.findById(o.deliveryQuoteId).orElseThrow();
  if(q.rateId==null)throw new ApiError(409,"Demo delivery has no carrier label. Configure Shippo test mode and place a new order.");
  if(!q.expiresAt.isAfter(Instant.now()))throw new ApiError(409,"Carrier rate expired. Place a new test order with a fresh quote.");
  var result=shipping.label(q.rateId);
  if(!"SUCCESS".equals(result.path("status").asText()))throw new ApiError(409,"Label was not completed. Check the Shippo dashboard before retrying.");
  t.carrierNumber=result.path("tracking_number").asText();t.carrier=result.path("rate").path("carrier").asText();
  if(t.carrier.isBlank())t.carrier=result.path("tracking_url_provider").asText().contains("usps")?"usps":q.carrier.toLowerCase(Locale.ROOT);
  t.labelUrl=result.path("label_url").asText();t.status="Label created";return orderView(o);
 }
 public Map<String,Object> refreshTracking(Long uid,Long oid){
  lock(uid);var o=ownedOrder(uid,oid);var t=tracks.findByOrderId(oid).orElseThrow();
  if(t.carrierNumber==null)throw new ApiError(409,"Carrier tracking starts after a shipping label is created.");
  if(t.checkedAt==null||t.checkedAt.isBefore(Instant.now().minusSeconds(60))){var result=shipping.track(t.carrier,t.carrierNumber);var status=result.path("tracking_status").path("status").asText();
   if(!status.isBlank())t.status=switch(status){case "DELIVERED"->"Delivered";case "TRANSIT"->"In transit";case "RETURNED"->"Returned";case "FAILURE"->"Delivery exception";default->"Label created";};t.checkedAt=Instant.now();}
  return orderView(o);
 }
 public StoreService(Accounts users,Products products,Carts carts,Addresses addresses,Cards cards,Purchases orders,Items items,Trackings tracks,PasswordEncoder encoder,ApplicationEventPublisher events,@Value("${app.url}") String appUrl) {
  this.users=users;this.products=products;this.carts=carts;this.addresses=addresses;this.cards=cards;this.orders=orders;this.items=items;this.tracks=tracks;this.encoder=encoder;this.events=events;
  var uri=java.net.URI.create(appUrl); if(!Set.of("http","https").contains(uri.getScheme())||uri.getHost()==null||uri.getRawQuery()!=null||uri.getRawFragment()!=null||uri.getUserInfo()!=null) throw new IllegalArgumentException("APP_URL must be an absolute http(s) origin without credentials, query or fragment");
  this.appUrl=appUrl.replaceAll("/+$", "");
 }
 static Map<String,Object> ok(String message) { var m=new LinkedHashMap<String,Object>();m.put("success",true);m.put("message",message);return m; }
 static String normalized(String email) {return email.trim().toLowerCase(Locale.ROOT);}
 Account lock(Long uid) {return users.lock(uid).orElseThrow(()->new ApiError(401,"Please sign in again."));}
 static void password(String value,String confirm) {
  if(value==null||confirm==null||!value.equals(confirm)||value.getBytes(StandardCharsets.UTF_8).length>72||!value.matches("(?s)(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}")) throw new ApiError(422,"Use matching passwords of 8–72 bytes, including an uppercase letter, a number and a special character.");
 }
 void notify(Account user,String subject,String body,Map<String,Object> response) {response.put("email_sent",false);events.publishEvent(new Notifications.Notice(user.email,subject,body,response));}
 public Map<String,Object> register(Registration r) {
  password(r.password(),r.confirm_password()); var email=normalized(r.email());
  if(users.findByEmail(email).isPresent()) throw new ApiError(409,"This email address is already used by another account.");
  var a=new Account();a.firstName=r.first_name().trim();a.lastName=r.last_name().trim();a.email=email;a.password=encoder.encode(r.password());users.saveAndFlush(a);seedAccount(a.id);
  var out=ok("Registration successful. You can now sign in.");out.put("user_id",a.id);notify(a,"Welcome to Mobile User","Hi "+a.firstName+",\nWelcome to Mobile User.\n"+appUrl+"/login",out);return out;
 }
 public void seedAccount(Long uid) {
  String[][] sample={{"123 Main Street","New York","NY","10001","USA"},{"456 Oak Avenue","Los Angeles","CA","90001","USA"},{"789 Pine Road","Chicago","IL","60601","USA"},{"101 Maple Lane","Houston","TX","77001","USA"},{"202 Cedar Boulevard","Phoenix","AZ","85001","USA"}};
  for(String kind:List.of("checkout","alternative")) for(String[] row:sample) {
   if(addresses.findByUserIdAndKindOrderByIdAsc(uid,kind).stream().anyMatch(a->a.addressLine.equals(row[0])&&a.city.equals(row[1])&&a.state.equals(row[2])&&a.zip.equals(row[3])&&a.country.equals(row[4])))continue;
   var a=new Address();a.userId=uid;a.kind=kind;a.addressLine=row[0];a.city=row[1];a.state=row[2];a.zip=row[3];a.country=row[4];addresses.save(a);
  }
  for(String n:List.of("4111111111111111","4222222222222222","5555555555554444","378282246310005","6011111111111117")) if(!cards.existsByUserIdAndNumber(uid,n)){var c=new Card();c.userId=uid;c.number=n;cards.save(c);}
 }
 public Map<String,Object> profile(Long uid) { var a=users.findById(uid).orElseThrow(()->new ApiError(401,"Please sign in again."));return Map.of("id",a.id,"first_name",a.firstName,"last_name",a.lastName,"email",a.email); }
 public Map<String,Object> updateProfile(Long uid,Profile r) {
  var a=lock(uid);var email=normalized(r.email());users.findByEmail(email).filter(u->!u.id.equals(uid)).ifPresent(u->{throw new ApiError(409,"This email address is already used by another account.");});
  if((r.old_password()!=null&&!r.old_password().isEmpty())||(r.new_password()!=null&&!r.new_password().isEmpty())||(r.confirm_password()!=null&&!r.confirm_password().isEmpty())) {
   if(r.old_password()==null||!encoder.matches(r.old_password(),a.password))throw new ApiError(422,"Your current password is incorrect.");password(r.new_password(),r.confirm_password());a.password=encoder.encode(r.new_password());a.resetTokenHash=null;a.resetExpires=null;
  }
  a.firstName=r.first_name().trim();a.lastName=r.last_name().trim();a.email=email;return ok("Your account details have been updated.");
 }
 static String randomToken(){byte[] bytes=new byte[32];new SecureRandom().nextBytes(bytes);return HexFormat.of().formatHex(bytes);}
 static String digest(String token){try{return HexFormat.of().formatHex(MessageDigest.getInstance("SHA-256").digest(token.getBytes(StandardCharsets.UTF_8)));}catch(NoSuchAlgorithmException e){throw new IllegalStateException(e);}}
 public Map<String,Object> forgot(String email) {
  var out=ok("If this account exists, a reset link has been sent to the local inbox.");
  users.findByEmail(normalized(email)).ifPresent(found->{var a=lock(found.id);var token=randomToken();a.resetTokenHash=digest(token);a.resetExpires=Instant.now().plusSeconds(3600);notify(a,"Reset your Mobile User password","Reset your password within one hour:\n"+appUrl+"/reset-password?token="+token,out);});return out;
 }
 public Map<String,Object> reset(Reset r) {
  password(r.password(),r.confirm_password());var a=users.findByResetTokenHash(digest(r.token())).orElseThrow(()->new ApiError(422,"Invalid, expired or already used reset token."));
  if(a.resetExpires==null||!a.resetExpires.isAfter(Instant.now()))throw new ApiError(422,"Invalid, expired or already used reset token.");
  a.password=encoder.encode(r.password());a.resetTokenHash=null;a.resetExpires=null;return ok("Password reset successful. You may now sign in.");
 }
 Map<String,Object> productView(Product p) {return Map.of("id",p.id,"name",p.name,"description",p.description==null?"":p.description,"price",p.price);}
 public List<Map<String,Object>> catalog(){return products.findAllByOrderByIdAsc().stream().map(this::productView).toList();}
 public Product product(Long id){return products.findById(id).orElseThrow(()->new ApiError(404,"Product not found."));}
 public Map<String,Object> cart(Long uid) {
  var rows=new ArrayList<Map<String,Object>>();BigDecimal total=new BigDecimal("0.00");int count=0;
  for(var c:carts.findByUserIdOrderByIdAsc(uid)){var p=product(c.productId);var subtotal=p.price.multiply(BigDecimal.valueOf(c.quantity));rows.add(Map.of("product_id",p.id,"name",p.name,"price",p.price,"quantity",c.quantity,"subtotal",subtotal));total=total.add(subtotal);count+=c.quantity;}
  var out=ok("");out.put("data",rows);out.put("total",total);out.put("item_count",count);return out;
 }
 public Map<String,Object> changeCart(Long uid,Long pid,String method,Integer qty,Integer change) {
  lock(uid);product(pid);var existing=carts.findByUserIdAndProductId(uid,pid);
  if(method.equals("DELETE")){existing.ifPresent(carts::delete);}
  else if(method.equals("POST")){var c=existing.orElseGet(()->{var x=new CartItem();x.userId=uid;x.productId=pid;return x;});c.quantity=Math.min(999,c.quantity+1);carts.save(c);}
  else { if((qty==null)==(change==null))throw new ApiError(422,"Supply either quantity or change.");var c=existing.orElseThrow(()->new ApiError(404,"Cart item not found."));int n=qty!=null?qty:Math.max(0,c.quantity+change);if(n<0||n>999)throw new ApiError(422,"Quantity must be between 0 and 999.");if(n==0)carts.delete(c);else c.quantity=n; }
  carts.flush();return cart(uid);
 }
 Map<String,Object> addressView(Address a){return Map.of("id",a.id,"address_line",a.addressLine,"city",a.city,"state",a.state,"zip",a.zip,"country",a.country);}
 public List<Map<String,Object>> savedAddresses(Long uid,String kind){return addresses.findByUserIdAndKindOrderByIdAsc(uid,kind).stream().map(this::addressView).toList();}
 public List<Map<String,Object>> savedCards(Long uid){return cards.findByUserIdOrderByIdAsc(uid).stream().map(c->Map.<String,Object>of("id",c.id,"card_number",c.number)).toList();}
 Address ownedAddress(Long uid,Long aid,String kind){return addresses.findByIdAndUserId(aid,uid).filter(a->a.kind.equals(kind)).orElseThrow(()->new ApiError(422,"Select a saved delivery address belonging to your account."));}
 Map<String,Object> confirmation(Purchase o){var t=tracks.findByOrderId(o.id).orElseThrow();var out=ok("Order placed successfully.");out.put("order_id",o.id);out.put("tracking_number",t.number);out.put("order",orderView(o));out.put("email_sent",false);return out;}
 public Map<String,Object> checkout(Long uid,Checkout r) {
  var user=lock(uid);String fingerprint=digest(r.address_id()+":"+r.card_number()+":"+r.expected_total().setScale(2)+(r.delivery_quote_id()==null?"":":"+r.delivery_quote_id()));
  var old=orders.findByUserIdAndSubmissionKey(uid,r.submission_key());if(old.isPresent()){if(!old.get().submissionFingerprint.equals(fingerprint))throw new ApiError(409,"This submission key was already used with different details.");return confirmation(old.get());}
  var address=ownedAddress(uid,r.address_id(),"checkout");if(!cards.existsByUserIdAndNumber(uid,r.card_number()))throw new ApiError(422,"Select a test card belonging to your account.");
  var lines=carts.findByUserIdOrderByIdAsc(uid);if(lines.isEmpty())throw new ApiError(409,"Your cart is empty.");
  var snapshots=new ArrayList<PurchaseItem>();BigDecimal total=new BigDecimal("0.00");
  for(var c:lines){var p=products.lock(c.productId).orElseThrow(()->new ApiError(404,"Product not found."));var item=new PurchaseItem();item.productId=p.id;item.name=p.name;item.price=p.price;item.quantity=c.quantity;if(c.quantity<1||c.quantity>999)throw new ApiError(422,"Invalid cart quantity.");total=total.add(p.price.multiply(BigDecimal.valueOf(c.quantity)));snapshots.add(item);}
  DeliveryQuote quote=null;
  if(r.delivery_quote_id()!=null){quote=deliveryQuotes.findByIdAndUserId(r.delivery_quote_id(),uid).orElseThrow(()->new ApiError(422,"Select your delivery quote."));
   if(!quote.addressId.equals(address.id)||!quote.fingerprint.equals(cartFingerprint(uid))||!quote.expiresAt.isAfter(Instant.now()))throw new ApiError(409,"Delivery quote expired or checkout changed. Refresh delivery options.");
   total=total.add(quote.amount);
  }else if(shipping.enabled())throw new ApiError(422,"Select a delivery service before checkout.");
  if(total.compareTo(r.expected_total())!=0)throw new ApiError(409,"Your cart has changed. Review the updated total before placing your order.");
  var o=new Purchase();o.userId=uid;o.addressId=address.id;o.deliveryAddress=address.formatted();o.cardLastFour=r.card_number().substring(r.card_number().length()-4);o.total=total;o.submissionKey=r.submission_key();o.submissionFingerprint=fingerprint;orders.saveAndFlush(o);
  o.deliveryQuoteId=quote==null?null:quote.id;o.shippingAmount=quote==null?BigDecimal.ZERO:quote.amount;
  for(var s:snapshots){s.orderId=o.id;items.save(s);}var t=new Tracking();t.orderId=o.id;t.number=randomToken().substring(0,32).toUpperCase(Locale.ROOT);tracks.saveAndFlush(t);carts.deleteAll(lines);
  var out=confirmation(o);out.put("email_sent",false);
  var receipt=new OrderPdf.Receipt(o.id,o.createdAt.toString(),user.firstName+" "+user.lastName,o.deliveryAddress,quote==null?"Standard delivery":quote.carrier+" - "+quote.service,t.number,trackingLink(t),o.total,o.shippingAmount,snapshots.stream().map(i->new OrderPdf.Line(i.name,i.quantity,i.price)).toList());
  events.publishEvent(new Notifications.Notice(user.email,"Your Mobile User order #"+o.id,"Your order is confirmed. Total: $"+o.total+"\nTracking: "+t.number+"\n"+trackingLink(t),out,receipt));return out;
 }
 public String appUrl(){return appUrl;}
 String trackingLink(Tracking t){return appUrl+"/orders?tracking="+t.number;}
 Purchase ownedOrder(Long uid,Long id){return orders.findByIdAndUserIdAndDeletedAtIsNull(id,uid).orElseThrow(()->new ApiError(404,"Order not found."));}
 Map<String,Object> orderView(Purchase o) {
  // Internal order references stay stable when carrier tracking is assigned.
  var t=tracks.findByOrderId(o.id).orElseThrow();var out=new LinkedHashMap<String,Object>();if(o.deliveryQuoteId!=null){var q=deliveryQuotes.findById(o.deliveryQuoteId).orElseThrow();out.put("delivery_service",q.carrier+" - "+q.service);out.put("estimated_transit_days",q.days);}out.put("shipping_amount",o.shippingAmount==null?BigDecimal.ZERO:o.shippingAmount);out.put("carrier_tracking_number",t.carrierNumber);out.put("carrier",t.carrier);out.put("label_url",t.labelUrl);out.put("tracking_checked_at",t.checkedAt);out.put("can_cancel",t.status.equalsIgnoreCase("Processing"));out.put("can_create_test_label",o.deliveryQuoteId!=null&&t.labelUrl==null&&shipping.key.startsWith("shippo_test_"));out.put("id",o.id);out.put("order_id",o.id);out.put("total",o.total);out.put("created_at",o.createdAt.toString());out.put("status",t.status);out.put("tracking_number",t.number);out.put("delivery_address",o.deliveryAddress);out.put("whitelist_address_id",o.whitelistAddressId);out.put("can_update_address",o.deliveryQuoteId==null&&o.deletedAt==null&&t.deletedAt==null&&t.status.equalsIgnoreCase("Processing"));out.put("addresses_set_b",savedAddresses(o.userId,"alternative"));out.put("items",items.findByOrderIdOrderByIdAsc(o.id).stream().map(i->Map.of("name",i.name,"price",i.price,"quantity",i.quantity)).toList());return out;
 }
 public List<Map<String,Object>> myOrders(Long uid){return orders.findByUserIdAndDeletedAtIsNullOrderByIdDesc(uid).stream().map(this::orderView).toList();}
 public Map<String,Object> tracking(Long uid,String number){var t=tracks.findByNumber(number).filter(x->x.deletedAt==null).orElseThrow(()->new ApiError(404,"Order not found."));return orderView(ownedOrder(uid,t.orderId));}
 public Map<String,Object> updateAddress(Long uid,AddressChange r) {
  if(ownedOrder(uid,r.order_id()).deliveryQuoteId!=null)throw new ApiError(409,"Rated orders require a new delivery quote. Cancel this processing order and check out again.");
  var user=lock(uid);var o=ownedOrder(uid,r.order_id());var t=tracks.findByOrderId(o.id).orElseThrow();if(t.deletedAt!=null||!t.status.equalsIgnoreCase("Processing"))throw new ApiError(409,"The delivery address can only be changed while the order is processing.");
  var a=ownedAddress(uid,r.whitelist_address_id(),"alternative");var out=ok("Delivery address saved.");out.put("changed",false);out.put("email_sent",false);out.put("delivery_address",a.formatted());out.put("whitelist_address_id",a.id);
  if(Objects.equals(o.whitelistAddressId,a.id))return out;String previous=o.deliveryAddress;o.whitelistAddressId=a.id;o.deliveryAddress=a.formatted();out.put("changed",true);
  if(!previous.equals(o.deliveryAddress))notify(user,"Delivery address changed for order #"+o.id,"Previous address: "+previous+"\nNew address: "+o.deliveryAddress+"\nTracking: "+trackingLink(t),out);return out;
 }
 public Map<String,Object> cancel(Long uid,Long oid){var user=lock(uid);var o=ownedOrder(uid,oid);if(!tracks.findByOrderId(oid).orElseThrow().status.equalsIgnoreCase("Processing"))throw new ApiError(409,"This order has entered fulfillment and cannot be cancelled.");o.deletedAt=Instant.now();var t=tracks.findByOrderId(oid).orElseThrow();t.deletedAt=o.deletedAt;var out=ok("Order cancelled.");notify(user,"Your Order #"+oid+" has been Cancelled","Your order #"+oid+" has been successfully cancelled.",out);return out;}
}
