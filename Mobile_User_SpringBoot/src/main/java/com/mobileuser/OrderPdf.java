package com.mobileuser;

import org.apache.pdfbox.pdmodel.*;
import org.apache.pdfbox.pdmodel.common.PDRectangle;
import org.apache.pdfbox.pdmodel.font.*;
import org.springframework.stereotype.Component;
import com.google.zxing.BarcodeFormat;
import com.google.zxing.oned.Code128Writer;
import java.io.*;
import java.math.BigDecimal;
import java.util.List;

/** Renders the committed checkout snapshot without database or network access. */
@Component
public class OrderPdf {
 public record Line(String name,int quantity,BigDecimal price) {}
 public record Receipt(Long orderId,String date,String customer,String address,String service,
                       String tracking,String trackingLink,BigDecimal total,BigDecimal shipping,List<Line> lines) {
  public Receipt { lines=List.copyOf(lines); }
 }
 public byte[] generate(Receipt receipt) throws IOException {
  try(var document=new PDDocument();var out=new ByteArrayOutputStream()) {
   document.getDocumentInformation().setTitle("Mobile User - Order #"+receipt.orderId());
   try(var layout=new Layout(document)) {
    layout.text("MOBILE USER",22,true);layout.text("Order confirmation #"+receipt.orderId(),16,true);
    layout.text("Placed: "+receipt.date(),10,false);layout.text("Demo store - no real payment was charged.",10,false);layout.gap(12);
    layout.text("DELIVER TO",11,true);layout.text(receipt.customer(),12,false);layout.text(receipt.address(),12,false);layout.gap(10);
    layout.text("ITEMS",11,true);
    for(var item:receipt.lines()) {layout.text(item.name(),12,true);layout.text(item.quantity()+" x $"+item.price().toPlainString()+"    Line total: $"+item.price().multiply(BigDecimal.valueOf(item.quantity())).toPlainString(),11,false);layout.gap(6);}
    layout.space(105);layout.gap(8);layout.text("Merchandise: $"+receipt.total().subtract(receipt.shipping()).toPlainString(),12,false);
    layout.text("Delivery: $"+receipt.shipping().toPlainString()+" - "+receipt.service(),12,false);
    layout.text("ORDER TOTAL: $"+receipt.total().toPlainString(),16,true);layout.gap(14);
    layout.space(240);layout.text("ORDER TRACKING REFERENCE",11,true);layout.text(receipt.tracking(),11,false);
    layout.barcode(receipt.tracking());layout.text("Track your order (sign in required):",10,false);layout.text(receipt.trackingLink(),10,false);layout.gap(12);
    layout.text("Carrier tracking and postage labels become available after fulfillment.",10,false);
    layout.text("This order confirmation is not a carrier shipping label or valid postage.",10,false);
   }
   document.save(out);return out.toByteArray();
  }
 }
 private static class Layout implements AutoCloseable {
  final PDDocument document;final PDFont regular=new PDType1Font(Standard14Fonts.FontName.HELVETICA);
  final PDFont bold=new PDType1Font(Standard14Fonts.FontName.HELVETICA_BOLD);
  PDPageContentStream stream;float y;
  Layout(PDDocument document)throws IOException{this.document=document;page();}
  void page()throws IOException{
   if(stream!=null)stream.close();var page=new PDPage(PDRectangle.LETTER);document.addPage(page);stream=new PDPageContentStream(document,page);y=742;
   stream.beginText();stream.setFont(regular,9);stream.newLineAtOffset(48,26);stream.showText("Mobile User | Order confirmation | Page "+document.getNumberOfPages());stream.endText();
  }
  void space(float height)throws IOException{if(y-height<58)page();}
  void gap(float height)throws IOException{space(height);y-=height;}
  String printable(String value,PDFont font){var result=new StringBuilder();for(int code:value.codePoints().toArray()){String ch=new String(Character.toChars(code));try{font.encode(ch);result.append(ch);}catch(Exception e){result.append(code==10||code==13?' ':'?');}}return result.toString();}
  void text(String value,int size,boolean strong)throws IOException{
   var font=strong?bold:regular;var text=printable(value==null?"":value,font);var line=new StringBuilder();
   for(int i=0;i<text.length();i++){var next=line.toString()+text.charAt(i);if(font.getStringWidth(next)/1000*size>510&&!line.isEmpty()){line(line.toString(),font,size);line.setLength(0);}line.append(text.charAt(i));}
   if(!line.isEmpty())line(line.toString(),font,size);
  }
  void line(String text,PDFont font,int size)throws IOException{space(size+7);stream.beginText();stream.setFont(font,size);stream.newLineAtOffset(48,y);stream.showText(text);stream.endText();y-=size+7;}
  void barcode(String tracking)throws IOException{
   space(80);var bars=new Code128Writer().encode(tracking,BarcodeFormat.CODE_128,480,52);
   float scale=480f/bars.getWidth();for(int x=0;x<bars.getWidth();x++)if(bars.get(x,0))stream.addRect(58+x*scale,y-58,scale,52);stream.fill();y-=78;
  }
  public void close()throws IOException{if(stream!=null)stream.close();}
 }
}
