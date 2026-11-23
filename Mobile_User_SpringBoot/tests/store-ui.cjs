const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM,VirtualConsole} = require('jsdom');
const root = path.join(__dirname,'..');
const tick = () => new Promise(resolve=>setTimeout(resolve,0));
let checks = 0;
const check = (ok,message) => { assert.ok(ok,message); checks++; };
const product = {id:1,name:'Wireless Earbuds',description:'Everyday audio.',price:'49.99'};
function cartOf(quantity=1) { return {success:true,data:quantity ? [{product_id:1,name:product.name,price:product.price,quantity,subtotal:(49.99*quantity).toFixed(2)}] : [],total:(49.99*quantity).toFixed(2),item_count:quantity}; }
function setup(page, overrides={}) {
    const file = {shop:'/shop',cart:'/cart',checkout:'/checkout',account:'/account'}[page];
    const content = fs.readFileSync(path.join(root,'src/main/resources/templates',page+'.html'),'utf8').replace(/<script[\s\S]*?<\/script>/g,'');
    const shell = '<span data-cart-count></span><span data-user-name></span><span data-user-initials></span><button data-signout>Sign out</button><div id="globalNotice" hidden></div><dialog id="confirmDialog"><h2 id="confirmTitle"></h2><p id="confirmDescription"></p><button data-dialog-cancel></button><button data-dialog-confirm></button></dialog>';
    const virtualConsole = new VirtualConsole();
    virtualConsole.on('jsdomError',error=>{ if (!error.message.includes('navigation')) throw error; });
    const dom = new JSDOM(content,{url:'http://localhost:8087'+file,runScripts:'outside-only',virtualConsole});
    const w=dom.window; w.document.body.dataset.page=page;
    const state={cart:cartOf(),products:[product,{id:2,name:'<img src=x onerror=alert(1)>',description:'A sample item.',price:'10.00'}],profile:{id:1,first_name:'Sample',last_name:'User',email:'sample@example.invalid'},calls:[],stale:false, ...overrides};
    w.crypto.randomUUID = require('node:crypto').randomUUID;
    w.HTMLDialogElement.prototype.showModal=function(){this.open=true;};
    w.HTMLDialogElement.prototype.close=function(value){this.returnValue=value||'';this.open=false;this.dispatchEvent(new w.Event('close'));};
    w.fetch=async(url,options={})=>{
        const endpoint=url.replace(/^\/api\//,''), method=options.method||'GET';
        const payload=options.body ? JSON.parse(options.body) : null;
        state.calls.push({endpoint,method,payload});
        let result, status=200;
        if(endpoint==='products') result={success:true,data:state.products};
        if(endpoint==='account'&&method==='GET') result={success:true,data:state.profile};
        if(endpoint==='cart') {
            if(method==='POST') state.cart=cartOf(state.cart.item_count+1);
            if(method==='PATCH') state.cart=cartOf(payload.quantity);
            if(method==='DELETE') state.cart=cartOf(0);
            result=state.cart;
        }
        if(endpoint==='addresses') result={success:true,data:[{id:7,address_line:'123 Main Street',city:'New York',state:'NY',zip:'10001',country:'USA'}]};
        if(endpoint==='shipping/rates') result={success:true,data:[{id:91,carrier:'demo',service:'Express delivery (demo)',amount:'12.00',estimated_days:2,demo:true}]};
        if(endpoint==='cards') result={success:true,data:[{card_number:'4111111111111111'}]};
        if(endpoint==='checkout') {
            if(state.stale) {status=409;state.cart=cartOf(2);result={success:false,message:'Your cart has changed. Review the updated total.'};}
            else {state.cart=cartOf(0);result={success:true,order_id:'5',tracking_number:'TRACK123',email_sent:true,...(state.labelMode ? {order:{id:5,tracking_number:'TRACK123',delivery_address:'123 Main Street',delivery_service:'USPS Ground',can_create_test_label:true}} : {})};}
        }
        if(endpoint==='orders/5/test-label') { if(state.labelMode==='failure') {status=503;result={success:false,message:'Provider unavailable'};} else result={success:true,data:{id:5,tracking_number:'TRACK123',carrier_tracking_number:'9400111899223856928499',delivery_address:'123 Main Street',label_url:'https://example.invalid/test-label.pdf',status:'Label created'}}; }
        if(endpoint==='account'&&method!=='GET') {
            if(payload.email==='taken@example.invalid'){status=409;result={success:false,message:'This email address is already used by another account.'};}
            else {state.profile={...state.profile,first_name:payload.first_name,last_name:payload.last_name,email:payload.email};result={success:true};}
        }
        if(endpoint==='auth/logout') result={success:true};
        assert.ok(result,'Unexpected API endpoint '+endpoint);
        return {ok:status===200,status,json:async()=>JSON.parse(JSON.stringify(result))};
    };
    w.eval(fs.readFileSync(path.join(root,'src/main/resources/static/assets/app.js'),'utf8'));
    if(page==='checkout') { w.eval(fs.readFileSync(path.join(root,'src/main/resources/static/assets/vendor/JsBarcode.code128.min.js'),'utf8')); w.eval(fs.readFileSync(path.join(root,'src/main/resources/static/assets/shipment-receipt.js'),'utf8')); }
    w.eval(fs.readFileSync(path.join(root,'src/main/resources/static/assets',page==='account'?'account.js':'commerce.js'),'utf8'));
    return {w,dom,state,doc:w.document};
}
(async()=>{
    {
        const {w,dom,state,doc}=setup('shop'); await tick();
        check(doc.querySelectorAll('.product-card').length===2,'Products not rendered');
        check(doc.querySelectorAll('[data-share]').length===2,'Product share actions missing');
        check(doc.querySelector('.product-info h2 a').getAttribute('href')==='/products/detail?id=1','Product detail link missing');
        const copied=[];
        Object.defineProperty(w.navigator,'clipboard',{value:{writeText:async url=>copied.push(url)},configurable:true});
        doc.querySelector('[data-share="1"]').click();await tick();
        check(copied[0]==='http://localhost:8087/products/detail?id=1','Share link incorrect or includes private session data');
        const nativeShares=[];
        Object.defineProperty(w.navigator,'share',{value:async data=>nativeShares.push(data),configurable:true});
        doc.querySelector('[data-share="1"]').click();await tick();
        check(nativeShares[0].title==='Wireless Earbuds' && nativeShares[0].url===copied[0],'Native sharing payload incorrect');
        Object.defineProperty(w.navigator,'share',{value:async()=>{throw new w.DOMException('Cancelled','AbortError');},configurable:true});
        doc.querySelector('[data-share="1"]').click();await tick();
        check(copied.length===1,'Cancelled native share unexpectedly copied a link');
        check(doc.querySelectorAll('img').length===0,'Stored product name interpreted as HTML');
        const search=doc.getElementById('productSearch'); search.value='earbuds';search.dispatchEvent(new w.Event('input'));
        check(doc.querySelectorAll('.product-card').length===1,'Search failed');
        doc.querySelector('[data-add]').click();await tick();
        check(state.cart.item_count===2 && doc.querySelector('[data-cart-count]').textContent==='2','Add-to-cart count failed');
        search.value='missing';search.dispatchEvent(new w.Event('input'));
        check(doc.body.textContent.includes('No matches found'),'Search empty state missing');
        search.value='';search.dispatchEvent(new w.Event('input'));
        const sort=doc.getElementById('productSort');sort.value='price-asc';sort.dispatchEvent(new w.Event('change'));
        check(doc.querySelector('[data-add]').dataset.add==='2','Product sorting failed');
        dom.window.close();
    }
    {
        const {dom,state,doc}=setup('cart');await tick();
        check(doc.getElementById('cartTotal').textContent==='$49.99','Cart total missing');
        doc.querySelector('[data-quantity="2"]').click();await tick();
        check(state.cart.item_count===2 && doc.getElementById('cartTotal').textContent==='$99.98','Quantity change total failed');
        doc.querySelector('[data-remove]').click();await tick();
        check(doc.getElementById('cartLayout').hidden && !doc.getElementById('cartEmpty').hidden,'Empty cart state failed');
        dom.window.close();
    }
    {
        const {w,dom,state,doc}=setup('checkout');await tick();
        check(doc.getElementById('Checkout').disabled,'Checkout enabled without selections');
        doc.getElementById('address').value='7';doc.getElementById('address').dispatchEvent(new w.Event('change'));
        check(doc.getElementById('addressPreview').textContent.includes('123 Main Street'),'Address preview missing');
        doc.getElementById('card').value='4111111111111111';doc.getElementById('card').dispatchEvent(new w.Event('change'));
        check(doc.getElementById('Checkout').disabled,'Delivery selection must be required'); await tick();
        doc.getElementById('deliveryService').value='91';doc.getElementById('deliveryService').dispatchEvent(new w.Event('change'));
        check(!doc.getElementById('Checkout').disabled,'Checkout not enabled after delivery selection');
        check(doc.getElementById('checkoutTotal').textContent==='$61.99','Delivery missing from total');
        check(doc.getElementById('card').textContent.includes('4111111111111111'),'Full test card number missing');
        const form=doc.getElementById('checkoutForm');form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));await tick();
        const calls=state.calls.filter(call=>call.endpoint==='checkout');
        check(calls.length===1,'Duplicate checkout submitted');
        check(calls[0].payload.expected_total==='61.99' && calls[0].payload.delivery_quote_id===91,'Reviewed total not submitted');
        check(!doc.getElementById('orderSuccess').hidden && doc.getElementById('orderSuccess').textContent.includes('TRACK123'),'Order confirmation missing');
        check(doc.querySelector('[data-cart-count]').textContent==='0','Cart badge not cleared');
        check(doc.querySelectorAll('#checkoutShipment svg rect').length > 10,'Tracking barcode missing');
        check(doc.querySelector('.packing-label').textContent.includes('123 Main Street'),'Delivery label address missing');
        check(doc.querySelector('.label-disclaimer').textContent.includes('NOT VALID POSTAGE'),'Demo label must be identified');
        dom.window.close();
    }
    for (const labelMode of ['success','failure']) {
        const {w,dom,state,doc}=setup('checkout',{labelMode});await tick();
        doc.getElementById('address').value='7';doc.getElementById('address').dispatchEvent(new w.Event('change'));await tick();
        doc.getElementById('deliveryService').value='91';doc.getElementById('deliveryService').dispatchEvent(new w.Event('change'));
        doc.getElementById('card').value='4111111111111111';doc.getElementById('card').dispatchEvent(new w.Event('change'));
        doc.getElementById('checkoutForm').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));await tick();await tick();
        check(state.calls.filter(c=>c.endpoint==='checkout').length===1,'Label generation must not resubmit checkout');
        if(labelMode==='success') {
            check(doc.querySelector('.label-preview').getAttribute('src')==='https://example.invalid/test-label.pdf','Carrier label PDF not visible');
            check(doc.querySelector('.shipment-details').textContent.includes('9400111899223856928499'),'Carrier tracking missing');
            check(doc.querySelector('.delivery-label a').getAttribute('rel')==='noopener noreferrer','External label link isolation missing');
        } else {
            check(doc.querySelector('.shipment-message').textContent.includes('Your order is confirmed'),'Label failure obscured saved order');
            check(!!doc.querySelector('.packing-label'),'Label preview lost after provider failure');
        }
        dom.window.close();
    }
    {
        const {w,dom,doc}=setup('checkout',{stale:true});await tick();
        doc.getElementById('address').value='7';doc.getElementById('address').dispatchEvent(new w.Event('change'));await tick();doc.getElementById('deliveryService').value='91';doc.getElementById('deliveryService').dispatchEvent(new w.Event('change'));doc.getElementById('card').value='4111111111111111';doc.getElementById('card').dispatchEvent(new w.Event('change'));
        doc.getElementById('checkoutForm').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));await tick();
        check(doc.getElementById('checkoutTotal').textContent==='$99.98','Changed checkout total not refreshed');
        check(doc.getElementById('pageNotice').textContent.includes('cart has changed'),'Changed cart warning missing');
        check(doc.getElementById('orderSuccess').hidden && doc.getElementById('Checkout').disabled,'Checkout recovery failed');
        dom.window.close();
    }
    {
        const {w,dom,state,doc}=setup('account');await tick();
        check(doc.getElementById('summaryFirstName').textContent==='Sample' && doc.getElementById('profileForm').hidden && doc.getElementById('profileFields').disabled,'Profile not read-only by default');
        check(doc.getElementById('passwordForm').hidden,'Password form shown without choosing to edit');
        doc.getElementById('editProfile').click();await tick();
        check(!doc.getElementById('profileForm').hidden && doc.getElementById('profileForm').querySelector('[type="submit"]').disabled,'Edit mode or unchanged-save state failed');
        doc.getElementById('firstName').value='Discard';doc.getElementById('firstName').dispatchEvent(new w.Event('input',{bubbles:true}));doc.getElementById('resetProfile').click();await tick();
        check(doc.getElementById('confirmDialog').open,'Unsaved changes were discarded without a decision');
        doc.querySelector('[data-dialog-cancel]').click();await tick();
        check(!doc.getElementById('profileForm').hidden && doc.getElementById('firstName').value==='Discard','Keep editing lost changes');
        doc.getElementById('resetProfile').click();await tick();doc.querySelector('[data-dialog-confirm]').click();await tick();
        check(doc.getElementById('firstName').value==='Sample' && doc.getElementById('profileForm').hidden,'Discard changes failed');
        check(!state.calls.some(call=>call.endpoint==='account'&&call.method!=='GET'),'Cancel saved changes');
        doc.getElementById('editProfile').click();await tick();
        const form=doc.getElementById('profileForm');doc.getElementById('firstName').value='<script>sample</script>';form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));await tick();
        check(state.profile.first_name==='<script>sample</script>' && doc.querySelector('[data-user-name]').textContent.includes('<script>'),'Profile save/header update failed');
        check(form.hidden && doc.getElementById('summaryFirstName').textContent==='<script>sample</script>','Saved profile did not return to read-only summary');
        check(doc.querySelectorAll('script').length===0,'Saved name interpreted as HTML');
        doc.getElementById('editProfile').click();await tick();
        doc.getElementById('email').value='taken@example.invalid';form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));await tick();
        check(doc.getElementById('profileFeedback').classList.contains('error'),'Profile error feedback missing');
        doc.getElementById('editPassword').click();await tick();
        check(doc.getElementById('confirmDialog').open,'Switching forms lost unsaved personal details');
        doc.querySelector('[data-dialog-confirm]').click();await tick();
        check(form.hidden && !doc.getElementById('passwordForm').hidden,'Account forms did not switch exclusively');
        const password=doc.getElementById('passwordForm');doc.getElementById('oldPassword').value='OldPass1!';doc.getElementById('newPassword').value='NewPass1!';doc.getElementById('confirmPassword').value='WrongPass1!';
        const before=state.calls.length;password.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));await tick();
        check(state.calls.length===before && doc.getElementById('passwordFeedback').textContent.includes('do not match'),'Mismatched password submitted');
        doc.getElementById('confirmPassword').value='NewPass1!';password.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));await tick();
        check(doc.getElementById('oldPassword').value==='' && doc.getElementById('passwordFeedback').textContent.includes('updated'),'Password success/clearing failed');
        const signout=doc.querySelector('[data-signout]');signout.click();await tick();
        check(doc.getElementById('confirmDialog').open,'Sign-out confirmation missing');
        doc.querySelector('[data-dialog-cancel]').click();await tick();
        check(!state.calls.some(call=>call.endpoint==='auth/logout'),'Cancel signed the user out');
        signout.click();await tick();doc.querySelector('[data-dialog-confirm]').click();await tick();
        check(state.calls.filter(call=>call.endpoint==='auth/logout'&&call.method==='POST').length===1,'Confirmed sign-out did not post');
        dom.window.close();
    }
    console.log(checks+' storefront/account interaction checks passed.');
})().catch(error=>{console.error(error);process.exit(1);});


