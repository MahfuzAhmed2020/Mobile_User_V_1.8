package com.mobileuser;
import com.fasterxml.jackson.databind.*;
import org.springframework.stereotype.Service;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.jdbc.support.GeneratedKeyHolder;
import org.springframework.transaction.annotation.Transactional;
import java.sql.*;
import java.time.Instant;
import java.util.*;
@Service
public class LegacyImporter {
 private final JdbcTemplate db; private final StoreService store;
 public LegacyImporter(JdbcTemplate db,StoreService store){this.db=db;this.store=store;}
 Long existing(String key,String table,long id){var rows=db.queryForList("SELECT target_id FROM legacy_imports WHERE source_key=? AND table_name=? AND legacy_id=?",Long.class,key,table,id);return rows.isEmpty()?null:rows.getFirst();}
 long mapped(String key,String table,long id){Long target=existing(key,table,id);if(target==null)throw new IllegalArgumentException("Unmapped legacy reference in "+table+": "+id);return target;}
 void remember(String key,String table,long old,long target){db.update("INSERT INTO legacy_imports(source_key,table_name,legacy_id,target_id) VALUES(?,?,?,?)",key,table,old,target);}
 long insert(String sql,Object...args){var holder=new GeneratedKeyHolder();db.update(connection->{var ps=connection.prepareStatement(sql,Statement.RETURN_GENERATED_KEYS);for(int i=0;i<args.length;i++)ps.setObject(i+1,args[i]);return ps;},holder);return Objects.requireNonNull(holder.getKey()).longValue();}
 static String text(JsonNode n,String field){if(!n.hasNonNull(field))throw new IllegalArgumentException("Missing import field "+field);return n.get(field).asText();}
 static long id(JsonNode n){return n.get("id").asLong();}
 static Timestamp timestamp(JsonNode n,String field){return n.hasNonNull(field)?Timestamp.from(Instant.parse(n.get(field).asText())):null;}
 static java.math.BigDecimal money(JsonNode n,String field){return new java.math.BigDecimal(text(n,field)).setScale(2,java.math.RoundingMode.UNNECESSARY);}
 @Transactional public Map<String,Object> importData(JsonNode root){
  if(root.path("format").asInt()!=1)throw new IllegalArgumentException("Unsupported export format");String key=text(root,"source_key");if(!key.matches("[a-zA-Z0-9_-]{1,100}"))throw new IllegalArgumentException("Invalid source key");var tables=root.get("tables");
  for(String table:List.of("users","products","addresses","addresses_whitelist","cart","orders","order_items","order_tracking"))if(tables==null||!tables.path(table).isArray())throw new IllegalArgumentException("Missing table "+table);
  for(var n:tables.get("products")){if(existing(key,"products",id(n))!=null)continue;
   var matches=db.queryForList("SELECT id FROM products WHERE name=? AND COALESCE(description,'')=? AND price=?",Long.class,text(n,"name"),n.path("description").asText(""),money(n,"price"));
   long target;if(!matches.isEmpty())target=matches.getFirst();else{if(db.queryForObject("SELECT COUNT(*) FROM products WHERE name=?",Integer.class,text(n,"name"))>0)throw new IllegalArgumentException("Catalog conflict; no products overwritten");target=insert("INSERT INTO products(name,description,price) VALUES(?,?,?)",text(n,"name"),n.path("description").asText(""),money(n,"price"));}remember(key,"products",id(n),target);
  }
  var accounts=new ArrayList<Long>();
  for(var n:tables.get("users")){if(existing(key,"users",id(n))!=null)continue;String email=text(n,"email").trim().toLowerCase(Locale.ROOT);String hash=text(n,"password");if(!hash.matches("\\$2[aby]\\$[0-9]{2}\\$[./A-Za-z0-9]{53}"))throw new IllegalArgumentException("Unsupported legacy password hash; import aborted");
   if(db.queryForObject("SELECT COUNT(*) FROM users WHERE email=?",Integer.class,email)>0)throw new IllegalArgumentException("Account email conflict; no accounts overwritten");
   long target=insert("INSERT INTO users(first_name,last_name,email,password,created_at) VALUES(?,?,?,?,?)",text(n,"first_name"),text(n,"last_name"),email,hash,timestamp(n,"created_at"));remember(key,"users",id(n),target);accounts.add(target);
  }
  for(String table:List.of("addresses","addresses_whitelist"))for(var n:tables.get(table)){if(existing(key,table,id(n))!=null)continue;long uid=mapped(key,"users",n.get("user_id").asLong());String kind=table.equals("addresses")?"checkout":"alternative";
   var matches=db.queryForList("SELECT id FROM saved_addresses WHERE user_id=? AND kind=? AND address_line=? AND city=? AND state=? AND zip=? AND country=?",Long.class,uid,kind,text(n,"address_line"),text(n,"city"),text(n,"state"),text(n,"zip"),text(n,"country"));
   long target=matches.isEmpty()?insert("INSERT INTO saved_addresses(user_id,kind,address_line,city,state,zip,country) VALUES(?,?,?,?,?,?,?)",uid,kind,text(n,"address_line"),text(n,"city"),text(n,"state"),text(n,"zip"),text(n,"country")):matches.getFirst();remember(key,table,id(n),target);
  }
  for(var n:tables.get("orders")){if(existing(key,"orders",id(n))!=null)continue;long uid=mapped(key,"users",n.get("user_id").asLong());long aid=mapped(key,"addresses",n.get("address_id").asLong());Long alt=n.hasNonNull("whitelist_address_id")?mapped(key,"addresses_whitelist",n.get("whitelist_address_id").asLong()):null;
   var a=db.queryForMap("SELECT * FROM saved_addresses WHERE id=? AND user_id=?",alt==null?aid:alt,uid);if(db.queryForObject("SELECT COUNT(*) FROM saved_addresses WHERE id=? AND user_id=?",Integer.class,aid,uid)!=1)throw new IllegalArgumentException("Foreign legacy checkout address");
   String address=String.join(", ",a.get("address_line").toString(),a.get("city").toString(),a.get("state").toString(),a.get("zip").toString(),a.get("country").toString());
   long target=insert("INSERT INTO orders(user_id,address_id,whitelist_address_id,delivery_address,total,submission_key,submission_fingerprint,deleted_at,created_at) VALUES(?,?,?,?,?,?,?,?,?)",uid,aid,alt,address,money(n,"total"),"legacy-"+StoreService.digest(key+":"+id(n)),StoreService.digest(key+":"+id(n)),timestamp(n,"deleted_at"),timestamp(n,"created_at"));remember(key,"orders",id(n),target);
  }
  for(var n:tables.get("order_items")){if(existing(key,"order_items",id(n))!=null)continue;long target=insert("INSERT INTO order_items(order_id,product_id,product_name,price,quantity) VALUES(?,?,?,?,?)",mapped(key,"orders",n.get("order_id").asLong()),mapped(key,"products",n.get("product_id").asLong()),text(n,"product_name"),money(n,"price"),n.get("quantity").asInt());remember(key,"order_items",id(n),target);}
  for(var n:tables.get("order_tracking")){if(existing(key,"order_tracking",id(n))!=null)continue;long target=insert("INSERT INTO order_tracking(order_id,tracking_number,status,deleted_at) VALUES(?,?,?,?)",mapped(key,"orders",n.get("order_id").asLong()),text(n,"tracking_number"),text(n,"status"),timestamp(n,"deleted_at"));remember(key,"order_tracking",id(n),target);}
  for(var n:tables.get("cart")){if(existing(key,"cart",id(n))!=null)continue;long target=insert("INSERT INTO cart(user_id,product_id,quantity) VALUES(?,?,?)",mapped(key,"users",n.get("user_id").asLong()),mapped(key,"products",n.get("product_id").asLong()),n.get("quantity").asInt());remember(key,"cart",id(n),target);}
  for(var n:tables.get("orders")){long oid=mapped(key,"orders",id(n));if(db.queryForObject("SELECT COUNT(*) FROM order_tracking WHERE order_id=?",Integer.class,oid)!=1||db.queryForObject("SELECT COUNT(*) FROM order_items WHERE order_id=?",Integer.class,oid)<1)throw new IllegalArgumentException("Legacy order missing tracking or price snapshots; import aborted");}
  for(Long uid:accounts)store.seedAccount(uid);
  return Map.of("success",true,"new_accounts",accounts.size(),"source_key",key);
 }
}
