package com.mobileuser;

import org.springframework.stereotype.Component;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.mail.SimpleMailMessage;
import org.springframework.mail.javamail.JavaMailSender;
import org.springframework.transaction.event.*;
import java.util.Map;
@Component
public class Notifications {
 public record Notice(String recipient,String subject,String text,Map<String,Object> result,OrderPdf.Receipt receipt) {
  public Notice(String recipient,String subject,String text,Map<String,Object> result){this(recipient,subject,text,result,null);}
 }
 private final OrderPdf pdf;
 private final JavaMailSender mail; private final boolean enabled; private final String from;
 public Notifications(JavaMailSender mail,@Value("${app.mail.enabled}") boolean enabled,@Value("${app.mail.from}") String from,OrderPdf pdf) {this.mail=mail;this.enabled=enabled;this.from=from;this.pdf=pdf;}
 @TransactionalEventListener(phase=TransactionPhase.AFTER_COMMIT)
 public void send(Notice notice) {
  boolean sent=false;
  if(notice.receipt()!=null)notice.result().put("pdf_attached",false);
  if(enabled) try {
   if(notice.receipt()!=null){
    var attachment=pdf.generate(notice.receipt());var msg=mail.createMimeMessage();
    var helper=new org.springframework.mail.javamail.MimeMessageHelper(msg,true,"UTF-8");
    helper.setFrom(from);helper.setTo(notice.recipient());helper.setSubject(notice.subject());helper.setText(notice.text()+"\n\nYour PDF order confirmation is attached.");
    helper.addAttachment("mobile-user-order-"+notice.receipt().orderId()+".pdf",new org.springframework.core.io.ByteArrayResource(attachment),"application/pdf");mail.send(msg);notice.result().put("pdf_attached",true);
   }else{var msg=new SimpleMailMessage();msg.setFrom(from);msg.setTo(notice.recipient());msg.setSubject(notice.subject());msg.setText(notice.text());mail.send(msg);}sent=true;
  }
  catch(Exception ex) { org.slf4j.LoggerFactory.getLogger(getClass()).warn("Notification delivery failed after commit: {}",ex.getClass().getSimpleName()); }
  notice.result().put("email_sent",sent);
 }
}
