package com.practice.todo

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

    private data class Task(val text:String,var done:Boolean)
    private val tasks=mutableListOf<Task>()
    private lateinit var taskList:LinearLayout
    private lateinit var summary:TextView
    private fun save() { val array=JSONArray(); tasks.forEach { array.put(org.json.JSONObject().put("text",it.text).put("done",it.done)) }; prefs.edit().putString("tasks",array.toString()).apply() }
    private fun render() {
        taskList.removeAllViews(); summary.text="${tasks.count { it.done }} of ${tasks.size} completed"
        tasks.forEachIndexed { index,task ->
            val row=LinearLayout(this).apply { orientation=LinearLayout.HORIZONTAL }
            val check=CheckBox(this).apply { text=task.text; isChecked=task.done; contentDescription="Complete task ${task.text}"; setOnCheckedChangeListener { _,checked -> task.done=checked; save(); summary.text="${tasks.count { it.done }} of ${tasks.size} completed" } }
            row.addView(check,LinearLayout.LayoutParams(0,-2,1f))
            row.addView(Button(this).apply { text="Delete"; contentDescription="Delete task ${task.text}"; setOnClickListener { tasks.removeAt(index); save(); render() } })
            taskList.addView(row)
        }
    }
    private fun start() {
        val saved=JSONArray(prefs.getString("tasks","[]")); for(i in 0 until saved.length()) { val t=saved.getJSONObject(i); tasks.add(Task(t.getString("text"),t.getBoolean("done"))) }
        title("To Do Lab","Learn lists, checkboxes, deletion, and persistence.")
        val input=field("New task"); val message=label("Add your first task")
        button("Add task") { val text=input.text.toString().trim(); if(text.isEmpty()) { message.text="Enter a task first" } else { tasks.add(Task(text,false)); save(); render(); input.setText(""); message.text="Task added" } }
        summary=label(""); taskList=LinearLayout(this).apply { orientation=LinearLayout.VERTICAL }; panel.addView(taskList); render()
        button("Clear completed") { tasks.removeAll { it.done }; save(); render() }
    }

}
