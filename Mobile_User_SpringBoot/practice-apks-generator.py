from pathlib import Path
import shutil

root = Path('kotlin-practice-apps')
template = Path(r'C:\Users\mahfu\Desktop\Appium_Test')
root.mkdir(exist_ok=True)
for name in ['gradlew', 'gradlew.bat', 'build.gradle.kts', 'local.properties']:
    shutil.copy2(template / name, root / name)
shutil.copytree(template / 'gradle', root / 'gradle', dirs_exist_ok=True)
apps = [('counter','Practice Counter'),('calculator','Practice Calculator'),('todo','Practice To Do'),('quiz','Practice Quiz'),('shop','Practice Shop')]
(root/'settings.gradle.kts').write_text('pluginManagement { repositories { google(); mavenCentral(); gradlePluginPortal() } }\ndependencyResolutionManagement { repositoriesMode.set(RepositoriesMode.FAIL_ON_PROJECT_REPOS); repositories { google(); mavenCentral() } }\nrootProject.name = "KotlinPractice"\n'+ '\n'.join(f'include(":{key}")' for key,_ in apps))
common = '''
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
BODY
}
'''
bodies = {
'counter': '''
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
''',
'calculator': '''
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
''',
'todo': '''
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
''',
'quiz': '''
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
''',
'shop': '''
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
'''
}
for key,label in apps:
    module=root/key
    source=module/'src/main/java/com/practice'/key
    source.mkdir(parents=True,exist_ok=True)
    (module/'build.gradle.kts').write_text((template/'app/build.gradle.kts').read_text().replace('com.mobileuser.android',f'com.practice.{key}'))
    (module/'src/main/AndroidManifest.xml').write_text(f'''<manifest xmlns:android="http://schemas.android.com/apk/res/android"><application android:allowBackup="false" android:label="{label}" android:theme="@android:style/Theme.Material.Light.NoActionBar" android:supportsRtl="true"><activity android:name=".MainActivity" android:exported="true"><intent-filter><action android:name="android.intent.action.MAIN"/><category android:name="android.intent.category.LAUNCHER"/></intent-filter></activity></application></manifest>''')
    (source/'MainActivity.kt').write_text(f'package com.practice.{key}\n'+common.replace('BODY',bodies[key]))
(root/'README.md').write_text('''# Kotlin Android practice apps

Five independent offline apps. No backend, account, or payment required. Debug APKs are for learning.

| APK | Package | Practice |
| --- | --- | --- |
| 01-counter.apk | com.practice.counter | Buttons, saved state, reset |
| 02-calculator.apk | com.practice.calculator | Inputs, arithmetic, invalid input |
| 03-todo.apk | com.practice.todo | Add, complete, delete, persistence |
| 04-quiz.apk | com.practice.quiz | Choices, navigation, scoring |
| 05-shop.apk | com.practice.shop | Quantities, totals, demo checkout |

Open this folder in Android Studio. Each module has its own MainActivity.kt under src/main/java/com/practice. Build all apps: `.\\gradlew.bat assembleDebug`. Requires JDK 17 or 21 and Android SDK 35. Update local.properties for another machine.

Install: `adb install -r "C:\\Users\\mahfu\\Desktop\\apk_files\\01-counter.apk"`.

For Appium Inspector use platformName Android, appium:automationName UiAutomator2, appium:udid emulator-5554, and appium:app pointing to the desired APK. Start the emulator and Appium first. All buttons and input fields have accessibility descriptions. Try empty input, repeated taps, restarting the app, checking calculated totals, and verifying saved tasks.
''')
print(root.resolve())
