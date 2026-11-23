# Kotlin Android practice apps

Five independent offline apps. No backend, account, or payment required. Debug APKs are for learning.

| APK | Package | Practice |
| --- | --- | --- |
| 01-counter.apk | com.practice.counter | Buttons, saved state, reset |
| 02-calculator.apk | com.practice.calculator | Inputs, arithmetic, invalid input |
| 03-todo.apk | com.practice.todo | Add, complete, delete, persistence |
| 04-quiz.apk | com.practice.quiz | Choices, navigation, scoring |
| 05-shop.apk | com.practice.shop | Quantities, totals, demo checkout |

Open this folder in Android Studio. Each module has its own MainActivity.kt under src/main/java/com/practice. Build all apps: `.\gradlew.bat assembleDebug`. Requires JDK 17 or 21 and Android SDK 35. Update local.properties for another machine.

Install: `adb install -r "C:\Users\mahfu\Desktop\apk_files\01-counter.apk"`.

For Appium Inspector use platformName Android, appium:automationName UiAutomator2, appium:udid emulator-5554, and appium:app pointing to the desired APK. Start the emulator and Appium first. All buttons and input fields have accessibility descriptions. Try empty input, repeated taps, restarting the app, checking calculated totals, and verifying saved tasks.
