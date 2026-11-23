package com.mobileuser;

import jakarta.mail.Session;
import jakarta.mail.internet.MimeMessage;
import jakarta.mail.Multipart;
import org.apache.pdfbox.Loader;
import org.apache.pdfbox.rendering.PDFRenderer;
import org.apache.pdfbox.text.PDFTextStripper;
import org.junit.jupiter.api.Test;
import org.springframework.mail.MailSendException;
import org.springframework.mail.javamail.JavaMailSender;
import java.math.BigDecimal;
import java.nio.file.*;
import java.util.*;
import javax.imageio.ImageIO;
import static org.junit.jupiter.api.Assertions.*;
import static org.mockito.Mockito.*;

class OrderPdfTest {
 static OrderPdf.Receipt receipt(int count){return new OrderPdf.Receipt(123L,"2026-11-02T12:00:00Z","Sample Customer","123 Main Street, New York, NY 10001, USA","demo - Express delivery","ABC12345678901234567890123456789","http://localhost:8087/orders?tracking=ABC12345678901234567890123456789",new BigDecimal("61.99"),new BigDecimal("12.00"),java.util.stream.IntStream.range(0,count).mapToObj(i->new OrderPdf.Line("Wireless Earbuds"+(count>1?" - extended product description ".repeat(5):""),1,new BigDecimal("49.99"))).toList());}
 @Test void pdfContainsOrderDataAndRendersAndPaginates()throws Exception {
  var pdf=new OrderPdf();var bytes=pdf.generate(receipt(1));var dir=Path.of(".runtime/pdf-qa");Files.createDirectories(dir);Files.write(dir.resolve("order-confirmation.pdf"),bytes);
  try(var doc=Loader.loadPDF(bytes)){
   assertEquals(1,doc.getNumberOfPages());var text=new PDFTextStripper().getText(doc);
   for(var expected:List.of("Order confirmation #123","Sample Customer","123 Main Street","Wireless Earbuds","61.99","12.00","ABC12345678901234567890123456789","not a carrier shipping label"))assertTrue(text.contains(expected),expected);
   ImageIO.write(new PDFRenderer(doc).renderImageWithDPI(0,120),"png",dir.resolve("order-confirmation.png").toFile());
  }
  try(var doc=Loader.loadPDF(pdf.generate(receipt(60)))){
   assertTrue(doc.getNumberOfPages()>1);var text=new PDFTextStripper().getText(doc);assertTrue(text.contains("ORDER TOTAL"));
   ImageIO.write(new PDFRenderer(doc).renderImageWithDPI(doc.getNumberOfPages()-1,100),"png",dir.resolve("long-order-last-page.png").toFile());
   var sheet=new java.awt.image.BufferedImage(612,396*((doc.getNumberOfPages()+1)/2),java.awt.image.BufferedImage.TYPE_INT_RGB);
   var graphics=sheet.createGraphics();graphics.setColor(java.awt.Color.WHITE);graphics.fillRect(0,0,sheet.getWidth(),sheet.getHeight());
   for(int i=0;i<doc.getNumberOfPages();i++)graphics.drawImage(new PDFRenderer(doc).renderImageWithDPI(i,72),(i%2)*306,(i/2)*396,306,396,null);
   graphics.dispose();ImageIO.write(sheet,"png",dir.resolve("long-order-pages.png").toFile());
  }
 }
 @Test void emailHasPdfAttachmentAndDeliveryFailureRemainsBestEffort()throws Exception {
  var mail=mock(JavaMailSender.class);when(mail.createMimeMessage()).thenAnswer(i->new MimeMessage((Session)null));
  var service=new Notifications(mail,true,"shop@example.invalid",new OrderPdf());var result=new HashMap<String,Object>();
  service.send(new Notifications.Notice("customer@example.invalid","Order #123","Your order is confirmed",result,receipt(1)));
  assertEquals(true,result.get("email_sent"));assertEquals(true,result.get("pdf_attached"));
  var captor=org.mockito.ArgumentCaptor.forClass(MimeMessage.class);verify(mail).send(captor.capture());var message=captor.getValue();message.saveChanges();
  var multipart=(Multipart)message.getContent();assertEquals(2,multipart.getCount());
  var attachment=multipart.getBodyPart(1);assertEquals("mobile-user-order-123.pdf",attachment.getFileName());assertTrue(attachment.isMimeType("application/pdf"));
  try(var doc=Loader.loadPDF(attachment.getInputStream().readAllBytes())){assertTrue(new PDFTextStripper().getText(doc).contains("61.99"));}
  doThrow(new MailSendException("Synthetic outage")).when(mail).send(any(MimeMessage.class));
  assertDoesNotThrow(()->service.send(new Notifications.Notice("customer@example.invalid","Order #123","Confirmed",result,receipt(1))));
  assertEquals(false,result.get("email_sent"));assertEquals(false,result.get("pdf_attached"));
 }
}
