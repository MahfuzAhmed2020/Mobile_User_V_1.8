package com.mobileuser;

import jakarta.validation.constraints.*;
import java.math.BigDecimal;
public final class Requests {
 public record Registration(@NotBlank @Size(max=255) String first_name,@NotBlank @Size(max=255) String last_name,@NotBlank @Email @Size(max=255) String email,@NotBlank @Size(max=72) String password,@NotBlank String confirm_password) {}
 public record Login(@NotBlank @Email String email,@NotBlank String password) {}
 public record EmailRequest(@NotBlank @Email @Size(max=255) String email) {}
 public record Reset(@NotBlank @Size(min=64,max=64) String token,@NotBlank @Size(max=72) String password,@NotBlank String confirm_password) {}
 public record Profile(@NotBlank @Size(max=255) String first_name,@NotBlank @Size(max=255) String last_name,@NotBlank @Email @Size(max=255) String email,String old_password,String new_password,String confirm_password) {}
 public record ProductId(@NotNull @Positive Long product_id) {}
 public record Quantity(@NotNull @Positive Long product_id,@Min(0) @Max(999) Integer quantity,@Min(-999) @Max(999) Integer change) {}
 public record Checkout(@NotNull @Positive Long address_id,@NotBlank @Size(max=20) String card_number,@NotNull @DecimalMin("0.01") @Digits(integer=8,fraction=2) BigDecimal expected_total,@NotBlank @Size(min=16,max=100) @Pattern(regexp="[a-zA-Z0-9_-]+") String submission_key, @Positive Long delivery_quote_id) {
  public Checkout(Long address_id,String card_number,BigDecimal expected_total,String submission_key){this(address_id,card_number,expected_total,submission_key,null);}
 }
 public record QuoteRequest(@NotNull @Positive Long address_id) {}
 public record AddressChange(@NotNull @Positive Long order_id,@NotNull @Positive Long whitelist_address_id) {}
 public record OrderId(@NotNull @Positive Long order_id) {}
 public record TrackingRequest(@NotBlank @Size(max=40) String tracking_number) {}
}
