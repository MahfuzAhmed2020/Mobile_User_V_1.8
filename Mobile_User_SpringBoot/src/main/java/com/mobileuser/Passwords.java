package com.mobileuser;
import org.springframework.context.annotation.*;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
@Configuration public class Passwords {
 @Bean PasswordEncoder passwordEncoder(){return new BCryptPasswordEncoder(12);}
}
