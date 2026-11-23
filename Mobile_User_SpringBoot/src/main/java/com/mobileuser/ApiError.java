package com.mobileuser;

import org.springframework.http.*;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.bind.MethodArgumentNotValidException;
import org.springframework.dao.DataIntegrityViolationException;
import org.springframework.http.converter.HttpMessageNotReadableException;
import java.util.Map;
public class ApiError extends RuntimeException {
 public final int status;
 public ApiError(int status,String message) { super(message);this.status=status; }
 @RestControllerAdvice(assignableTypes=ApiController.class)
 public static class Advice {
  @ExceptionHandler(ApiError.class) ResponseEntity<?> business(ApiError e) { return ResponseEntity.status(e.status).body(Map.of("success",false,"message",e.getMessage())); }
  @ExceptionHandler({MethodArgumentNotValidException.class,HttpMessageNotReadableException.class,IllegalArgumentException.class}) ResponseEntity<?> invalid(Exception e) { return ResponseEntity.unprocessableEntity().body(Map.of("success",false,"message","Enter valid fields. Check required values, email, quantity and password confirmation.")); }
  @ExceptionHandler(DataIntegrityViolationException.class) ResponseEntity<?> conflict(Exception e) { return ResponseEntity.status(409).body(Map.of("success",false,"message","This email or submission already exists. Refresh and try again.")); }
 }
}
