package com.mobileuser;

import jakarta.persistence.*;
import java.math.BigDecimal;
import java.time.Instant;
public final class Domain {
 @Entity @Table(name="delivery_quotes") public static class DeliveryQuote extends Row {
  public Long userId; public Long addressId;
  @Column(length=64) public String fingerprint;
  public String carrier; public String service; public String rateId;
  @Column(precision=10,scale=2) public BigDecimal amount;
  public int days; public Instant expiresAt;
 }
 private Domain() {}
 @MappedSuperclass public abstract static class Row { @Id @GeneratedValue(strategy=GenerationType.IDENTITY) public Long id; }
 @Entity(name="Account") @Table(name="users") public static class Account extends Row {
  @Column(name="first_name",nullable=false) public String firstName;
  @Column(name="last_name",nullable=false) public String lastName;
  @Column(nullable=false,unique=true) public String email;
  @Column(nullable=false) public String password;
  @Column(name="reset_token_hash",length=64,unique=true) public String resetTokenHash;
  @Column(name="reset_expires") public Instant resetExpires;
  @Column(name="created_at",nullable=false) public Instant createdAt=Instant.now();
 }
 @Entity(name="Product") @Table(name="products") public static class Product extends Row {
  @Column(nullable=false) public String name;
  public String description;
  @Column(nullable=false,precision=10,scale=2) public BigDecimal price;
 }
 @Entity @Table(name="cart",uniqueConstraints=@UniqueConstraint(columnNames={"user_id","product_id"})) public static class CartItem extends Row {
  @Column(name="user_id",nullable=false) public Long userId;
  @Column(name="product_id",nullable=false) public Long productId;
  @Column(nullable=false) public int quantity;
 }
 @Entity @Table(name="saved_addresses") public static class Address extends Row {
  @Column(name="user_id",nullable=false) public Long userId;
  @Column(nullable=false,length=20) public String kind;
  @Column(name="address_line",nullable=false) public String addressLine;
  @Column(nullable=false,length=100) public String city;
  @Column(nullable=false,length=100) public String state;
  @Column(nullable=false,length=20) public String zip;
  @Column(nullable=false,length=100) public String country;
  public String formatted() { return String.join(", ",addressLine,city,state,zip,country); }
 }
 @Entity @Table(name="demo_cards",uniqueConstraints=@UniqueConstraint(columnNames={"user_id","card_number"})) public static class Card extends Row {
  @Column(name="user_id",nullable=false) public Long userId;
  @Column(name="card_number",nullable=false,length=20) public String number;
 }
 @Entity @Table(name="orders",uniqueConstraints=@UniqueConstraint(columnNames={"user_id","submission_key"})) public static class Purchase extends Row {
  public Long deliveryQuoteId;
  @Column(precision=10,scale=2) public BigDecimal shippingAmount;
  @Column(name="user_id",nullable=false) public Long userId;
  @Column(name="address_id",nullable=false) public Long addressId;
  @Column(name="whitelist_address_id") public Long whitelistAddressId;
  @Column(name="delivery_address",nullable=false,length=700) public String deliveryAddress;
  @Column(name="card_last_four",length=4) public String cardLastFour;
  @Column(nullable=false,precision=10,scale=2) public BigDecimal total;
  @Column(name="submission_key",nullable=false,length=100) public String submissionKey;
  @Column(name="submission_fingerprint",nullable=false,length=64) public String submissionFingerprint;
  @Column(name="deleted_at") public Instant deletedAt;
  @Column(name="created_at",nullable=false) public Instant createdAt=Instant.now();
 }
 @Entity @Table(name="order_items") public static class PurchaseItem extends Row {
  @Column(name="order_id",nullable=false) public Long orderId;
  @Column(name="product_id",nullable=false) public Long productId;
  @Column(name="product_name",nullable=false) public String name;
  @Column(nullable=false,precision=10,scale=2) public BigDecimal price;
  @Column(nullable=false) public int quantity;
 }
 @Entity @Table(name="order_tracking") public static class Tracking extends Row {
  public String carrier; public String carrierNumber;
  @Column(length=2000) public String labelUrl;
  public Instant checkedAt;
  @Column(name="order_id",nullable=false,unique=true) public Long orderId;
  @Column(name="tracking_number",nullable=false,unique=true,length=40) public String number;
  @Column(nullable=false,length=50) public String status="Processing";
  @Column(name="deleted_at") public Instant deletedAt;
 }
}

