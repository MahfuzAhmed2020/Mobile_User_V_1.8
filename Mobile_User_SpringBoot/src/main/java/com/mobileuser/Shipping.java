package com.mobileuser;

import com.fasterxml.jackson.databind.JsonNode;
import com.mobileuser.Domain.*;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Service;
import org.springframework.web.client.RestClient;
import org.springframework.http.client.SimpleClientHttpRequestFactory;
import java.math.BigDecimal;
import java.time.Instant;
import java.util.*;

/** Server-owned, expiring quotes. Demo checkout never purchases live postage. */
@Service
public class Shipping {
 final String key, origin; final RestClient client;
 public Shipping(@Value("${SHIPPO_API_KEY:}") String key,@Value("${SHIPPO_ORIGIN_JSON:}") String origin) {
  this.key=key;this.origin=origin;
  var factory=new SimpleClientHttpRequestFactory();factory.setConnectTimeout(5000);factory.setReadTimeout(15000);
  client=RestClient.builder().baseUrl("https://api.goshippo.com").requestFactory(factory)
   .defaultHeader("Authorization","ShippoToken "+key).defaultHeader("SHIPPO-API-VERSION","2018-02-08").build();
 }
 public boolean enabled(){return !key.isBlank();}
 JsonNode call(String path,Object body) {
  try {return body==null?client.get().uri(path).retrieve().body(JsonNode.class):client.post().uri(path).body(body).retrieve().body(JsonNode.class);}
  catch(org.springframework.web.client.RestClientException e){throw new ApiError(503,"Shipping provider is unavailable. Please try again; your order has not changed.");}
 }
 List<DeliveryQuote> rates(Long uid,Address address,Account user,String fingerprint,int quantity) {
  if(!enabled()) {
   return List.of(quote(uid,address.id,fingerprint,"demo","Standard delivery (demo)","0.00",5),quote(uid,address.id,fingerprint,"demo","Express delivery (demo)","12.00",2));
  }
  if(origin.isBlank())throw new ApiError(503,"Shipping origin is not configured.");
  if(!Set.of("US","USA","United States").contains(address.country))throw new ApiError(422,"Only domestic US shipping is supported.");
  JsonNode from;
  try {from=new com.fasterxml.jackson.databind.ObjectMapper().readTree(origin);}catch(Exception e){throw new ApiError(503,"Shipping origin configuration is invalid.");}
  var to=Map.of("name",user.firstName+" "+user.lastName,"street1",address.addressLine,"city",address.city,"state",address.state,"zip",address.zip,"country","US");
  // Conservative demo packing model; replace with catalog weights before enabling live fulfillment.
  if(quantity>20)throw new ApiError(422,"This cart requires a custom shipping quote (maximum 20 units).");
  var parcel=Map.of("length","12","width","10","height","8","distance_unit","in","weight",String.valueOf(quantity*2),"mass_unit","lb");
  var result=call("/shipments/",Map.of("address_from",from,"address_to",to,"parcels",List.of(parcel),"async",false));
  var quotes=new ArrayList<DeliveryQuote>();
  for(var r:result.path("rates")) {
   if(!"USD".equals(r.path("currency").asText()))continue;
   try {var q=quote(uid,address.id,fingerprint,r.path("provider").asText(),r.path("servicelevel").path("name").asText(),r.path("amount").asText(),r.path("estimated_days").asInt(0));q.rateId=r.path("object_id").asText();quotes.add(q);}catch(NumberFormatException ignored){}
  }
  if(quotes.isEmpty())throw new ApiError(422,"No delivery services are available for this address.");
  quotes.sort(Comparator.comparing(q->q.amount));return quotes.stream().limit(10).toList();
 }
 static DeliveryQuote quote(Long uid,Long aid,String fingerprint,String carrier,String service,String amount,int days){
  var q=new DeliveryQuote();q.userId=uid;q.addressId=aid;q.fingerprint=fingerprint;q.carrier=carrier;q.service=service;q.amount=new BigDecimal(amount).setScale(2);q.days=days;q.expiresAt=Instant.now().plusSeconds(900);return q;
 }
 public JsonNode label(String rate){
  if(!key.startsWith("shippo_test_"))throw new ApiError(409,"Demo payments support test labels only. Configure a Shippo test key.");
  return call("/transactions/",Map.of("rate",rate,"label_file_type","PDF","async",false));
 }
 public JsonNode track(String carrier,String number){return call("/tracks/"+org.springframework.web.util.UriUtils.encodePathSegment(carrier,java.nio.charset.StandardCharsets.UTF_8)+"/"+org.springframework.web.util.UriUtils.encodePathSegment(number,java.nio.charset.StandardCharsets.UTF_8)+"/",null);}
}
