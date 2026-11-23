package com.mobileuser;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.data.jpa.repository.config.EnableJpaRepositories;
@SpringBootApplication
@EnableJpaRepositories(considerNestedRepositories = true)
public class Application {
 public static void main(String[] args) { var context=SpringApplication.run(Application.class,args); if(context.getEnvironment().acceptsProfiles(org.springframework.core.env.Profiles.of("legacy-import")))context.close(); }
}
