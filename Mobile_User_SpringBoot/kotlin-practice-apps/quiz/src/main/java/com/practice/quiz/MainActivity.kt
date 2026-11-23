package com.practice.quiz

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

    private data class Question(val text:String,val options:List<String>,val correct:Int)
    private val questions=listOf(
        Question("Which keyword declares an immutable reference?",listOf("val","var","fun"),0),
        Question("Which function starts an Android activity lifecycle?",listOf("onCreate","onClick","println"),0),
        Question("Which widget accepts user text?",listOf("TextView","EditText","ImageView"),1),
        Question("What does a nullable Kotlin type end with?",listOf("!","?","#"),1),
        Question("Which tool can inspect native Android elements?",listOf("Appium Inspector","MySQL","Git"),0))
    private var index=0; private var score=0
    private fun start() { showQuestion() }
    private fun showQuestion() {
        panel.removeAllViews(); title("Kotlin Quiz Lab","Learn screen transitions, choices, and scoring.")
        if(index==questions.size) {
            val best=maxOf(score,prefs.getInt("best",0)); prefs.edit().putInt("best",best).apply()
            label("Score: $score / ${questions.size}",28f); label("Best score: $best / ${questions.size}")
            button("Restart quiz") { index=0; score=0; showQuestion() }; return
        }
        val q=questions[index]; label("Question ${index+1} of ${questions.size}"); label(q.text,24f)
        var answered=false; val feedback=label("Choose an answer")
        q.options.forEachIndexed { choice,text -> button(text) { if(!answered) { answered=true; if(choice==q.correct) { score++; feedback.text="Correct!" } else feedback.text="Correct answer: ${q.options[q.correct]}" } } }
        button("Next question") { if(answered) { index++; showQuestion() } else feedback.text="Choose an answer first" }
    }

}
