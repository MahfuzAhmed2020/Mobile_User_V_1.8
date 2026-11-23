const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const BASE=process.env.UI_BASE_URL || 'http://localhost:8087';
if(!/^http:\/\/(localhost|127\.0\.0\.1):[0-9]+$/.test(BASE))throw Error('Use an isolated localhost application with synthetic accounts.');
const email='browser-'+Date.now()+'@example.invalid';
const password='BrowserTest1!';
const errors=[];
let checks=0;
function check(condition,message){assert.ok(condition,message);checks++;}
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.BROWSER_EXECUTABLE?{executablePath:process.env.BROWSER_EXECUTABLE}:{channel:'msedge'})});
 try{
  const context=await browser.newContext({viewport:{width:1440,height:1000}});
  const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
  await page.goto(BASE+'/shop');await page.locator('.product-card').last().waitFor();check(await page.locator('.product-card').count()===15,'All 15 products');
  check(await page.locator('.product-art svg').count()===15,'All illustrations');
  await page.locator('#productSearch').fill('earbuds');check(await page.locator('.product-card').count()===1,'Search');await page.locator('#productSearch').fill('');
  await page.locator('#productSort').selectOption('price-asc');check((await page.locator('.product-info h2').first().innerText()).includes('Tempered Glass'),'Price sorting');
  await page.goto(BASE+'/products/1');check(await page.locator('#detailAdd').count()===0,'Public product cannot add anonymously');
  check(await page.locator('link[rel=canonical]').getAttribute('href')===BASE+'/products/1','Canonical URL');
  await page.evaluate(()=>{Object.defineProperty(navigator,'share',{value:undefined,configurable:true});Object.defineProperty(navigator,'clipboard',{value:undefined,configurable:true});});await page.locator('#detailShare').click();await page.locator('#shareDialog[open]').waitFor();check(await page.locator('#shareLink').inputValue()===BASE+'/products/1','Manual share fallback contains product only');await page.locator('#closeShare').click();
  await page.goto(BASE+'/register');await page.locator('#first_name').fill('Browser');await page.locator('#last_name').fill('Synthetic');await page.locator('#email').fill(email);await page.locator('#password').fill(password);await page.locator('#confirm_password').fill(password);await page.locator('input[type=checkbox]').check();await page.locator('#authForm button').click();await page.waitForFunction(()=>document.getElementById('authNotice').textContent.includes('successful'));checks++;
  await page.goto(BASE+'/login?redirect=%2Fproducts%2F1');await page.locator('#email').fill(email);await page.locator('#password').fill(password);await Promise.all([page.waitForURL(BASE+'/products/1'),page.locator('#authForm button').click()]);checks++;
  await page.locator('#detailAdd').click();await page.waitForFunction(()=>document.querySelector('[data-cart-count]').textContent==='1');
  await page.goto(BASE+'/cart');await page.locator('[data-quantity="2"]').click();await page.waitForFunction(()=>document.getElementById('cartTotal').textContent==='$99.98');checks++;
  await page.goto(BASE+'/checkout');await page.locator('#address option').nth(1).waitFor({state:'attached'});await page.locator('#address').selectOption({index:1});await page.locator('#card').selectOption({index:1});check(await page.locator('#addressPreview').isVisible()&&await page.locator('#cardPreview').isVisible(),'Checkout previews');
  await page.locator('#deliveryService option').nth(1).waitFor({state:'attached'});await page.locator('#deliveryService').selectOption({index:1});
  await page.locator('#Checkout').click();await page.locator('#orderSuccess').waitFor({state:'visible'});check((await page.locator('#orderSuccess').innerText()).includes('Thank you'),'Checkout confirmation');
  await page.goto(BASE+'/orders');await page.locator('.order').waitFor();check(await page.locator('[data-action=edit]').count()===0,'Rated order address locked');check((await page.locator('.order').innerText()).includes('Delivery:'),'Delivery charge displayed');
  await page.goto(BASE+'/account');await page.locator('#editProfile').waitFor({state:'visible'});await page.waitForFunction(()=>!document.getElementById('editProfile').disabled);check(await page.locator('#profileForm').isHidden()&&await page.locator('#passwordForm').isHidden(),'Read-only account');
  await page.locator('#editProfile').click();await page.locator('#firstName').fill('Unsaved');await page.locator('#editPassword').click();await page.locator('#confirmDialog[open]').waitFor();await page.locator('[data-dialog-cancel]').click();check(await page.locator('#firstName').inputValue()==='Unsaved','Keep editing');await page.locator('#resetProfile').click();await page.locator('[data-dialog-confirm]').click();await page.locator('#profileForm').waitFor({state:'hidden'});check(await page.locator('#profileForm').isHidden(),'Discard changes');
  await page.locator('#editProfile').click();await page.locator('#firstName').fill('Updated');await page.locator('#profileForm button[type=submit]').click();await page.waitForFunction(()=>document.getElementById('summaryFirstName').textContent==='Updated');checks++;
  await page.locator('#editPassword').click();await page.locator('#oldPassword').fill(password);await page.locator('#newPassword').fill('ChangedBrowser1!');await page.locator('#confirmPassword').fill('ChangedBrowser1!');await page.locator('#passwordForm button[type=submit]').click();await page.waitForFunction(()=>document.getElementById('passwordFeedback').textContent.includes('updated'));checks++;
  await page.goto(BASE+'/shop');await page.locator('.product-card').last().waitFor();await page.evaluate(()=>scrollTo(0,document.body.scrollHeight));check(Math.abs((await page.locator('.site-header').boundingBox()).y)<2,'Sticky header');
  const out=path.join(__dirname,'../.runtime/screenshots');fs.mkdirSync(out,{recursive:true});await page.screenshot({path:path.join(out,'shop-desktop.png'),fullPage:true});
  await page.setViewportSize({width:390,height:844});await page.goto(BASE+'/shop');await page.locator('.product-card').last().waitFor();check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Mobile layout fits');await page.screenshot({path:path.join(out,'shop-mobile.png'),fullPage:true});
  await page.locator('[data-add="1"]').click();await page.waitForFunction(()=>document.querySelector('[data-cart-count]').textContent==='1');
  await page.locator('[data-signout]').click();await page.locator('[data-dialog-cancel]').click();check(!page.url().includes('signed-out'),'Cancel sign-out');await page.locator('[data-signout]').click();await Promise.all([page.waitForURL(BASE+'/signed-out'),page.locator('[data-dialog-confirm]').click()]);checks++;
  await page.goto(BASE+'/login');await page.locator('#email').fill(email);await page.locator('#password').fill('ChangedBrowser1!');await Promise.all([page.waitForURL(BASE+'/shop'),page.locator('#authForm button').click()]);await page.waitForFunction(()=>document.querySelector('[data-cart-count]').textContent==='1');checks++;
  await page.goto(BASE+'/orders');await page.locator('[data-action=cancel-order]').click();await page.locator('[data-dialog-confirm]').click();await page.waitForFunction(()=>document.querySelector('#orders').textContent.includes('No orders yet'));checks++;
  await page.goto(BASE+'/forgot-password');await page.locator('#email').fill(email);await page.locator('#authForm button').click();await page.waitForFunction(()=>document.getElementById('authNotice').textContent.includes('reset link'));checks++;
  const inbox=await (await page.request.get('http://127.0.0.1:8025/api/v2/search?kind=to&query='+encodeURIComponent(email))).json();const resetEmail=inbox.items.find(item=>item.Content.Headers.Subject.some(subject=>subject.includes('Reset your Mobile User password')));check(!!resetEmail,'Reset email captured');const resetLink=resetEmail.Content.Body.match(/https?:[^\s]+reset-password\?token=[a-f0-9]+/)[0];
  await page.goto(resetLink);await page.locator('#password').fill('ResetBrowser2!');await page.locator('#confirm_password').fill('ResetBrowser2!');await page.locator('#authForm button').click();await page.waitForFunction(()=>document.getElementById('authNotice').textContent.includes('Password reset successful'));checks++;
  await page.goto(BASE+'/login?redirect=https%3A%2F%2Fexample.invalid');await page.locator('#email').fill(email);await page.locator('#password').fill('ChangedBrowser1!');await page.locator('#authForm button').click();await page.waitForFunction(()=>document.getElementById('authNotice').textContent.includes('Invalid email or password'));check(page.url().includes('/login'),'Old password rejected inline');await page.locator('#password').fill('ResetBrowser2!');await Promise.all([page.waitForURL(BASE+'/shop'),page.locator('#authForm button').click()]);checks++;
  await page.goto(BASE+'/docs');await page.locator('.swagger-ui .info .title').waitFor();check((await page.locator('.swagger-ui .info .title').innerText()).includes('Mobile User'),'Swagger UI');
  check(errors.length===0,'No frontend runtime errors: '+errors.join(', '));
  fs.writeFileSync(path.join(__dirname,'../.runtime/browser-results.json'),JSON.stringify({checks,errors,syntheticAccount:email,base:BASE},null,2));
  console.log(checks+' real-browser interaction checks passed, desktop and mobile; Swagger UI rendered.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});


