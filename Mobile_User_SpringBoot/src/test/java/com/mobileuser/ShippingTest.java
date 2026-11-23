package com.mobileuser;

import com.mobileuser.Domain.*;
import org.junit.jupiter.api.Test;
import java.math.BigDecimal;
import java.time.Instant;
import static org.junit.jupiter.api.Assertions.*;

class ShippingTest {
 @Test void providerRatesFilterCurrencyAndPreserveRateIdentity() throws Exception {
  var payload=new com.fasterxml.jackson.databind.ObjectMapper().readTree("{\"rates\":[{\"object_id\":\"rate-1\",\"provider\":\"USPS\",\"servicelevel\":{\"name\":\"Ground\"},\"currency\":\"USD\",\"amount\":\"8.25\",\"estimated_days\":3},{\"currency\":\"EUR\",\"amount\":\"1.00\"}]}");
  var shipping=new Shipping("shippo_test_placeholder","{\"country\":\"US\"}") {
   @Override com.fasterxml.jackson.databind.JsonNode call(String path,Object body){assertEquals("/shipments/",path);assertNotNull(body);return payload;}
  };
  var address=new Address();address.id=1L;address.country="USA";address.addressLine="123 Main";address.city="New York";address.state="NY";address.zip="10001";
  var user=new Account();user.firstName="Test";user.lastName="User";
  var quotes=shipping.rates(2L,address,user,"fingerprint",1);
  assertEquals(1,quotes.size());assertEquals("rate-1",quotes.getFirst().rateId);assertEquals(new BigDecimal("8.25"),quotes.getFirst().amount);assertEquals(3,quotes.getFirst().days);
 }
 @Test void demoQuotesAreOwnedExpiringAndDoNotCallProvider(){
  var shipping=new Shipping("","");var address=new Address();address.id=9L;
  var quotes=shipping.rates(7L,address,new Account(),"cart-hash",3);
  assertEquals(2,quotes.size());
  assertEquals(new BigDecimal("0.00"),quotes.getFirst().amount);
  assertEquals(new BigDecimal("12.00"),quotes.getLast().amount);
  for(var quote:quotes){assertEquals(7L,quote.userId);assertEquals(9L,quote.addressId);assertEquals("cart-hash",quote.fingerprint);assertTrue(quote.expiresAt.isAfter(Instant.now()));assertNull(quote.rateId);}
 }
 @Test void demoPaymentsCannotBuyLiveLabels(){
  var shipping=new Shipping("shippo_live_placeholder","");
  assertEquals(409,assertThrows(ApiError.class,()->shipping.label("rate")).status);
 }
 @Test void configuredProviderRequiresOriginBeforeNetworkCall(){
  var shipping=new Shipping("shippo_test_placeholder","");
  assertEquals(503,assertThrows(ApiError.class,()->shipping.rates(1L,new Address(),new Account(),"cart",1)).status);
 }
}
