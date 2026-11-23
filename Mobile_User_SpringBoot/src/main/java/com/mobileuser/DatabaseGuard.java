package com.mobileuser;
import javax.sql.DataSource;
import org.springframework.context.annotation.*;
import org.springframework.boot.autoconfigure.jdbc.DataSourceProperties;
@Configuration
public class DatabaseGuard {
 @Bean DataSource dataSource(DataSourceProperties properties) {
  String url=properties.getUrl();
  if(url==null||!url.matches("jdbc:mysql://[^/]+/mobile_user_springboot_[a-zA-Z0-9_]+(?:\\?.*)?"))throw new IllegalStateException("Use a separate mobile_user_springboot_* database. Existing PHP databases are prohibited.");
  return properties.initializeDataSourceBuilder().build();
 }
}
