package com.mobileuser;

import org.springframework.stereotype.Controller;
import org.springframework.web.bind.annotation.*;
import org.springframework.ui.Model;
import org.springframework.security.core.Authentication;
import org.springframework.security.authentication.AnonymousAuthenticationToken;
import java.util.*;
@Controller
@org.springframework.boot.autoconfigure.condition.ConditionalOnWebApplication
public class PageController {
 final StoreService store;public PageController(StoreService store){this.store=store;}
 @ModelAttribute void common(Model m,Authentication a){boolean signed=a!=null&&a.isAuthenticated()&&!(a instanceof AnonymousAuthenticationToken);m.addAttribute("signedIn",signed);if(signed){var p=store.profile(Long.valueOf(a.getName()));m.addAttribute("name",p.get("first_name")+" "+p.get("last_name"));m.addAttribute("initials",p.get("first_name").toString().substring(0,1)+p.get("last_name").toString().substring(0,1));}}
 @GetMapping({"/","/shop","/cart","/checkout","/orders","/account","/login","/register","/forgot-password","/reset-password","/signed-out"})
 String page(jakarta.servlet.http.HttpServletRequest r,Model m){String p=r.getRequestURI().substring(1);if(p.isEmpty())p="shop";String script=switch(p){case "shop","cart","checkout"->"commerce.js";case "account"->"account.js";case "orders"->"orders.js";case "signed-out"->"";default->"auth.js";};m.addAttribute("page",p);m.addAttribute("title",p.equals("account")?"Account settings":p.substring(0,1).toUpperCase()+p.substring(1));m.addAttribute("scripts",script.isEmpty()?List.of():List.of(script));return p;}
 @GetMapping("/products/detail") String product(@RequestParam Long id,Model m){return detail(id,m);}
 @GetMapping("/products/{id}") String pathProduct(@PathVariable Long id,Model m){return detail(id,m);}
 String detail(Long id,Model m){com.mobileuser.Domain.Product p;try{p=store.product(id);}catch(ApiError ex){throw new org.springframework.web.server.ResponseStatusException(org.springframework.http.HttpStatus.NOT_FOUND,"Product not found");}m.addAttribute("product",p);m.addAttribute("title",p.name);m.addAttribute("description",p.description);m.addAttribute("canonical",store.appUrl()+"/products/"+p.id);m.addAttribute("page","product");m.addAttribute("scripts",List.of("commerce.js","product.js"));return "product";}
}
