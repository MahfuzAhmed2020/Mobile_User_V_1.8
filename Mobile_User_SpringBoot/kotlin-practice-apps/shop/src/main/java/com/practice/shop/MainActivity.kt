package com.practice.shop

import android.app.Activity
import android.os.Bundle
import android.graphics.Color
import android.widget.*
import android.text.InputType
import org.json.JSONArray
import java.math.BigDecimal
import java.math.RoundingMode

class MainActivity : Activity() {
    private lateinit var panel: LinearLayout
    private val prefs by lazy { getSharedPreferences("practice", MODE_PRIVATE) }
    override fun onCreate(state: Bundle?) {
        super.onCreate(state)
        val scroll = ScrollView(this)
        panel = LinearLayout(this).apply { orientation=LinearLayout.VERTICAL; setPadding(28,32,28,32); setBackgroundColor(Color.rgb(243,246,252)) }
        scroll.addView(panel); setContentView(scroll)
        panel.setOnApplyWindowInsetsListener { v,i -> v.setPadding(28,i.systemWindowInsetTop+24,28,i.systemWindowInsetBottom+24); i }
        start()
    }
    private fun label(text:String, size:Float=18f):TextView = TextView(this).apply { this.text=text; textSize=size; setTextColor(Color.rgb(25,40,65)); setPadding(8,14,8,14); panel.addView(this) }
    private fun button(text:String, action:()->Unit) = Button(this).apply { this.text=text; isAllCaps=false; contentDescription=text; setOnClickListener { action() }; panel.addView(this) }
    private fun field(hint:String, numeric:Boolean=false):EditText = EditText(this).apply { this.hint=hint; contentDescription=hint; isSingleLine=true; if(numeric) inputType=InputType.TYPE_CLASS_NUMBER or InputType.TYPE_NUMBER_FLAG_DECIMAL or InputType.TYPE_NUMBER_FLAG_SIGNED; panel.addView(this) }
    private fun title(name:String, lesson:String) { label(name,28f); label(lesson,16f) }

    private data class Product(val name:String,val cents:Int,var quantity:Int=0)
    private val products=listOf(Product("Notebook",499),Product("Headphones",2999),Product("Water Bottle",1250))
    private lateinit var total:TextView
    private fun money(cents:Int)="%.2f".format(java.util.Locale.US,cents/100.0)
    private fun update() { total.text="Cart: ${products.sumOf { it.quantity }} items | Total: $${money(products.sumOf { it.quantity*it.cents })}" }
    private fun start() {
        title("Shop Lab","Offline demo: learn quantity controls, totals, and checkout.")
        total=label("",24f); update()
        products.forEach { product ->
            label("${product.name} - $${money(product.cents)}",22f)
            val quantity=label("Quantity: 0")
            button("Add ${product.name}") { product.quantity++; quantity.text="Quantity: ${product.quantity}"; update() }
            button("Remove ${product.name}") { if(product.quantity>0) product.quantity--; quantity.text="Quantity: ${product.quantity}"; update() }
        }
        val status=label("No payment or backend required.")
        button("Checkout") { if(products.sumOf { it.quantity }==0) status.text="Your cart is empty" else android.app.AlertDialog.Builder(this).setTitle("Demo checkout").setMessage("Total: $${money(products.sumOf { it.quantity*it.cents })}. This is a practice order; no payment is taken.").setPositiveButton("Confirm demo order") { _,_ -> products.forEach { it.quantity=0 }; panel.removeAllViews(); start(); android.widget.Toast.makeText(this,"Demo order placed",android.widget.Toast.LENGTH_SHORT).show() }.setNegativeButton("Cancel",null).show() }
    }

}
