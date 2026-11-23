package com.mobileuser;

import org.springframework.context.annotation.*;
import org.springframework.security.config.annotation.web.builders.HttpSecurity;
import org.springframework.security.web.SecurityFilterChain;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.security.web.csrf.*;
import org.springframework.security.web.context.HttpSessionSecurityContextRepository;
import org.springframework.security.authentication.*;
import org.springframework.security.core.userdetails.*;
import com.mobileuser.Repositories.Accounts;
@Configuration
@org.springframework.boot.autoconfigure.condition.ConditionalOnWebApplication
public class SecurityConfig {

 @Bean UserDetailsService details(Accounts users) { return email -> {
  var a=users.findByEmail(email.trim().toLowerCase(java.util.Locale.ROOT)).orElseThrow(()->new UsernameNotFoundException("Invalid credentials"));
  return User.withUsername(a.id.toString()).password(a.password).roles("USER").build();
 }; }
 @Bean AuthenticationManager authenticationManager(UserDetailsService users,PasswordEncoder encoder) {
  var provider=new org.springframework.security.authentication.dao.DaoAuthenticationProvider(users); provider.setPasswordEncoder(encoder);return new ProviderManager(provider);
 }
 @Bean HttpSessionSecurityContextRepository contexts() { return new HttpSessionSecurityContextRepository(); }
 @Bean SecurityFilterChain chain(HttpSecurity http) throws Exception {
  var csrf=CookieCsrfTokenRepository.withHttpOnlyFalse();
  http.csrf(c->c.csrfTokenRepository(csrf).csrfTokenRequestHandler(new CsrfTokenRequestAttributeHandler()))
   .authorizeHttpRequests(a->a.requestMatchers("/","/shop","/products/**","/login","/register","/forgot-password","/reset-password","/signed-out","/assets/**","/docs","/swagger-ui/**","/v3/api-docs/**","/openapi.json","/api/csrf","/api/auth/login","/api/auth/register","/api/auth/forgot-password","/api/auth/reset-password","/api/products/**","/error").permitAll().anyRequest().authenticated())
   .exceptionHandling(e->e.authenticationEntryPoint((req,res,ex)->{if(req.getRequestURI().startsWith("/api/")){res.setStatus(401);res.setContentType("application/json");res.getWriter().write("{\"success\":false,\"message\":\"Please sign in.\"}");}else res.sendRedirect("/login");})
    .accessDeniedHandler((req,res,ex)->{res.setStatus(403);res.setContentType("application/json");res.getWriter().write("{\"success\":false,\"message\":\"Request verification failed. Refresh and try again.\"}");}))
   .logout(l->l.logoutUrl("/api/auth/logout").deleteCookies("JSESSIONID","XSRF-TOKEN").logoutSuccessHandler((req,res,auth)->{res.setContentType("application/json");res.getWriter().write("{\"success\":true}");}))
   .headers(h->h.referrerPolicy(r->r.policy(org.springframework.security.web.header.writers.ReferrerPolicyHeaderWriter.ReferrerPolicy.NO_REFERRER)));
  return http.build();
 }
}
