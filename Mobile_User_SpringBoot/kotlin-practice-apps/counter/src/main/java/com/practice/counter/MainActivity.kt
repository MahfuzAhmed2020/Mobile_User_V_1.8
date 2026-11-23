package com.practice.counter

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

    private var count=0
    private fun start() {
        count=prefs.getInt("count",0)
        title("Counter Lab", "Learn click events, state, and local storage.")
        val display=label("Count: $count",32f)
        fun update() { display.text="Count: $count"; prefs.edit().putInt("count",count).apply() }
        button("Increase") { count++; update() }
        button("Decrease") { count--; update() }
        button("Reset counter") { count=0; update() }
        label("Practice: increase twice, decrease once, then reopen the app. Your count stays saved.")
    }

}
