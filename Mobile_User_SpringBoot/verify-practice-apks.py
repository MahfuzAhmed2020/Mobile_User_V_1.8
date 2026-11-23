import json
from pathlib import Path
from urllib.request import Request, urlopen
import xml.etree.ElementTree as ET

root = Path('kotlin-practice-apps').resolve()
def request(method,path,body=None):
    req=Request('http://127.0.0.1:4723'+path,data=json.dumps(body).encode() if body is not None else None,headers={'Content-Type':'application/json'},method=method)
    with urlopen(req,timeout=240) as response: return json.load(response)['value']

def find(session, name):
    value=request('POST',f'/session/{session}/element',{'using':'accessibility id','value':name})
    return value.get('element-6066-11e4-a52e-4f735466cecf') or value['ELEMENT']

def click(session,name):
    request('POST',f'/session/{session}/element/{find(session,name)}/click',{})

def enter(session,name,text):
    request('POST',f'/session/{session}/element/{find(session,name)}/value',{'text':text})

def check(session,text):
    source=request('GET',f'/session/{session}/source')
    assert text in source, f'Missing expected text: {text}'

report=[]
for key in ['counter','calculator','todo','quiz','shop']:
    caps={'platformName':'Android','appium:automationName':'UiAutomator2','appium:udid':'emulator-5554','appium:app':str(root/key/'build/outputs/apk/debug'/f'{key}-debug.apk'),'appium:adbExecTimeout':120000,'appium:uiautomator2ServerLaunchTimeout':120000}
    result=request('POST','/session',{'capabilities':{'alwaysMatch':caps}})
    session=result['sessionId']
    try:
        if key=='counter':
            click(session,'Reset counter'); click(session,'Increase'); check(session,'Count: 1')
        elif key=='calculator':
            enter(session,'First number','7'); enter(session,'Second number','3'); click(session,'Add'); check(session,'Result: 10')
        elif key=='todo':
            enter(session,'New task','Learn Kotlin'); click(session,'Add task'); check(session,'Learn Kotlin'); click(session,'Delete task Learn Kotlin'); check(session,'0 of 0 completed')
        elif key=='quiz':
            click(session,'val'); check(session,'Correct!'); click(session,'Next question'); check(session,'Question 2 of 5')
        else:
            click(session,'Add Notebook'); check(session,'Total: $4.99'); click(session,'Remove Notebook'); check(session,'Total: $0.00')
        report.append(f'{key}: PASS (installed, launched, interaction verified)')
        print(report[-1],flush=True)
    finally: request('DELETE',f'/session/{session}')
(root/'VERIFICATION.txt').write_text('\n'.join(report)+'\n')
