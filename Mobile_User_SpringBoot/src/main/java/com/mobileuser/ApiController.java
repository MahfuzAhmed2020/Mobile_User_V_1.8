package com.mobileuser;

import com.mobileuser.Requests.*;
import jakarta.validation.Valid;
import jakarta.servlet.http.*;
import org.springframework.web.bind.annotation.*;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.security.authentication.*;
import org.springframework.security.web.context.HttpSessionSecurityContextRepository;
import org.springframework.security.web.csrf.CsrfToken;
import java.util.*;
@RestController @RequestMapping("/api")
@org.springframework.boot.autoconfigure.condition.ConditionalOnWebApplication
public class ApiController {
 final StoreService store; final AuthenticationManager manager; final HttpSessionSecurityContextRepository contexts;
 public ApiController(StoreService store,AuthenticationManager manager,HttpSessionSecurityContextRepository contexts){this.store=store;this.manager=manager;this.contexts=contexts;}
 Long uid(Authentication a){return Long.valueOf(a.getName());}
 static Map<String,Object> data(Object value){return Map.of("success",true,"data",value);}
 @GetMapping("/csrf") public Map<String,Object> csrf(CsrfToken token){return Map.of("success",true,"token",token.getToken(),"headerName",token.getHeaderName());}
 @PostMapping("/auth/register") public Map<String,Object> register(@Valid @RequestBody Registration r){return store.register(r);}
 @PostMapping("/auth/login") public Map<String,Object> login(@Valid @RequestBody Login r,HttpServletRequest request,HttpServletResponse response){
  try {var auth=manager.authenticate(UsernamePasswordAuthenticationToken.unauthenticated(r.email(),r.password()));
   if(request.getSession(false)!=null)request.changeSessionId();else request.getSession(true);
   var context=SecurityContextHolder.createEmptyContext();context.setAuthentication(auth);SecurityContextHolder.setContext(context);contexts.saveContext(context,request,response);
   new org.springframework.security.web.csrf.CsrfAuthenticationStrategy(new org.springframework.security.web.csrf.CookieCsrfTokenRepository()).onAuthentication(auth,request,response);
   return Map.of("success",true,"user",store.profile(Long.valueOf(auth.getName())));
  } catch(org.springframework.security.core.AuthenticationException ex){throw new ApiError(401,"Invalid email or password.");}
 }
 @PostMapping("/auth/forgot-password") public Map<String,Object> forgot(@Valid @RequestBody EmailRequest r){return store.forgot(r.email());}
 @PostMapping("/auth/reset-password") public Map<String,Object> reset(@Valid @RequestBody Reset r){return store.reset(r);}
 @GetMapping("/products") public Map<String,Object> products(){return data(store.catalog());}
 @GetMapping("/products/{id}") public Map<String,Object> product(@PathVariable Long id){return data(store.productView(store.product(id)));}
 @GetMapping("/account") public Map<String,Object> profile(Authentication a){return data(store.profile(uid(a)));}
 @PatchMapping("/account") public Map<String,Object> patch(Authentication a,@Valid @RequestBody Profile r){return store.updateProfile(uid(a),r);}
 @PostMapping("/account") public Map<String,Object> update(Authentication a,@Valid @RequestBody Profile r){return store.updateProfile(uid(a),r);}
 @GetMapping("/cart") public Map<String,Object> cart(Authentication a){return store.cart(uid(a));}
 @PostMapping("/cart") public Map<String,Object> add(Authentication a,@Valid @RequestBody ProductId r){return store.changeCart(uid(a),r.product_id(),"POST",null,null);}
 @PatchMapping("/cart") public Map<String,Object> quantity(Authentication a,@Valid @RequestBody Quantity r){return store.changeCart(uid(a),r.product_id(),"PATCH",r.quantity(),r.change());}
 @DeleteMapping("/cart") public Map<String,Object> remove(Authentication a,@Valid @RequestBody ProductId r){return store.changeCart(uid(a),r.product_id(),"DELETE",null,null);}
 @GetMapping("/addresses") public Map<String,Object> addresses(Authentication a){return data(store.savedAddresses(uid(a),"checkout"));}
 @GetMapping("/cards") public Map<String,Object> cards(Authentication a){return data(store.savedCards(uid(a)));}
 @PostMapping("/checkout") public Map<String,Object> checkout(Authentication a,@Valid @RequestBody Checkout r){return store.checkout(uid(a),r);}
 @PostMapping("/shipping/rates") public Map<String,Object> rates(Authentication a,@Valid @RequestBody QuoteRequest r){return data(store.deliveryRates(uid(a),r.address_id()));}
 @PostMapping("/orders/{id}/test-label") public Map<String,Object> label(Authentication a,@PathVariable Long id){return data(store.createTestLabel(uid(a),id));}
 @PostMapping("/orders/{id}/tracking/refresh") public Map<String,Object> refresh(Authentication a,@PathVariable Long id){return data(store.refreshTracking(uid(a),id));}
 @GetMapping("/orders") public Map<String,Object> orders(Authentication a){return data(store.myOrders(uid(a)));}
 @PatchMapping("/orders") public Map<String,Object> address(Authentication a,@Valid @RequestBody AddressChange r){return store.updateAddress(uid(a),r);}
 @DeleteMapping("/orders") public Map<String,Object> cancel(Authentication a,@Valid @RequestBody OrderId r){return store.cancel(uid(a),r.order_id());}
 @GetMapping("/tracking") public Map<String,Object> tracking(Authentication a,@RequestParam String tracking){return data(store.tracking(uid(a),tracking));}
 @PostMapping("/tracking") public Map<String,Object> track(Authentication a,@Valid @RequestBody TrackingRequest r){return data(store.tracking(uid(a),r.tracking_number()));}
}
