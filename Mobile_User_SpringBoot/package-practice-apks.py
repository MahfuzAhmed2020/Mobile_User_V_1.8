from pathlib import Path
import shutil, json, zipfile

root=Path('kotlin-practice-apps').resolve()
dest=Path(r'C:\Users\mahfu\Desktop\apk_files')
dest.mkdir(parents=True,exist_ok=True)
keys=['counter','calculator','todo','quiz','shop']
for number,key in enumerate(keys,1):
    target=dest/f'{number:02}-{key}.apk'
    if target.exists(): raise RuntimeError(f'Refusing to overwrite {target}')
    shutil.copy2(root/key/'build/outputs/apk/debug'/f'{key}-debug.apk',target)
    caps={'platformName':'Android','appium:automationName':'UiAutomator2','appium:deviceName':'Pixel_9','appium:udid':'emulator-5554','appium:app':str(target),'appium:adbExecTimeout':120000,'appium:uiautomator2ServerLaunchTimeout':120000}
    (dest/f'{number:02}-{key}-inspector.json').write_text(json.dumps(caps,indent=2))
shutil.copy2(root/'README.md',dest/'README.md')
if (root/'VERIFICATION.txt').exists(): shutil.copy2(root/'VERIFICATION.txt',dest/'VERIFICATION.txt')
with zipfile.ZipFile(dest/'KotlinPracticeApps-source.zip','w',zipfile.ZIP_DEFLATED) as archive:
    for path in root.rglob('*'):
        relative=path.relative_to(root)
        if path.is_file() and not any(p in {'build','.gradle','.kotlin'} for p in relative.parts):
            archive.write(path,Path('KotlinPracticeApps')/relative)
for apk in dest.glob('*.apk'): print(f'{apk.name}: {apk.stat().st_size:,} bytes')
