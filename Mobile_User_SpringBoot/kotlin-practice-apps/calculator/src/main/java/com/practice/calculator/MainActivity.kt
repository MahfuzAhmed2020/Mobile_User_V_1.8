package com.practice.calculator

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

    private fun start() {
        title("Calculator Lab", "Learn inputs, validation, and arithmetic.")
        val first=field("First number",true); val second=field("Second number",true)
        val result=label("Result: enter two numbers",24f)
        fun calculate(op:String) {
            val a=first.text.toString().toBigDecimalOrNull(); val b=second.text.toString().toBigDecimalOrNull()
            if(a==null || b==null) { result.text="Enter two valid numbers"; return }
            if(op=="Divide" && b.compareTo(BigDecimal.ZERO)==0) { result.text="Cannot divide by zero"; return }
            val value=when(op) { "Add"->a+b; "Subtract"->a-b; "Multiply"->a*b; else->a.divide(b,8,RoundingMode.HALF_UP) }
            result.text="Result: ${value.stripTrailingZeros().toPlainString()}"
        }
        listOf("Add","Subtract","Multiply","Divide").forEach { op -> button(op) { calculate(op) } }
        button("Clear inputs") { first.setText(""); second.setText(""); result.text="Result: enter two numbers" }
        label("Practice: decimals, negative values, empty fields, and division by zero.")
    }

}
