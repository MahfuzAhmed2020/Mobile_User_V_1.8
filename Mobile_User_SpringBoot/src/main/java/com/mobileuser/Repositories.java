package com.mobileuser;

import com.mobileuser.Domain.*;
import java.util.*;
import jakarta.persistence.LockModeType;
import org.springframework.data.jpa.repository.*;
import org.springframework.data.repository.query.Param;
public final class Repositories {
 public interface DeliveryQuotes extends JpaRepository<DeliveryQuote,Long> { Optional<DeliveryQuote> findByIdAndUserId(Long id,Long uid); }
 public interface Accounts extends JpaRepository<Account,Long> {
  Optional<Account> findByEmail(String email);
  @Lock(LockModeType.PESSIMISTIC_WRITE) @Query("select a from Account a where a.id=:id") Optional<Account> lock(@Param("id") Long id);
  @Lock(LockModeType.PESSIMISTIC_WRITE) Optional<Account> findByResetTokenHash(String hash);
 }
 public interface Products extends JpaRepository<Product,Long> { List<Product> findAllByOrderByIdAsc();
  @Lock(LockModeType.PESSIMISTIC_READ) @Query("select p from Product p where p.id=:id") Optional<Product> lock(@Param("id") Long id);
 }
 public interface Carts extends JpaRepository<CartItem,Long> { List<CartItem> findByUserIdOrderByIdAsc(Long uid); Optional<CartItem> findByUserIdAndProductId(Long uid,Long pid); }
 public interface Addresses extends JpaRepository<Address,Long> { List<Address> findByUserIdAndKindOrderByIdAsc(Long uid,String kind); Optional<Address> findByIdAndUserId(Long id,Long uid); }
 public interface Cards extends JpaRepository<Card,Long> { List<Card> findByUserIdOrderByIdAsc(Long uid); boolean existsByUserIdAndNumber(Long uid,String number); }
 public interface Purchases extends JpaRepository<Purchase,Long> { List<Purchase> findByUserIdAndDeletedAtIsNullOrderByIdDesc(Long uid); Optional<Purchase> findByIdAndUserIdAndDeletedAtIsNull(Long id,Long uid); Optional<Purchase> findByUserIdAndSubmissionKey(Long uid,String key); }
 public interface Items extends JpaRepository<PurchaseItem,Long> { List<PurchaseItem> findByOrderIdOrderByIdAsc(Long oid); }
 public interface Trackings extends JpaRepository<Tracking,Long> { Optional<Tracking> findByOrderId(Long oid); Optional<Tracking> findByNumber(String number); }
}
