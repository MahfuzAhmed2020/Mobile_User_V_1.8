const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('../.runtime/validation/node_modules/jsdom');
const Parser = require('../.runtime/validation/node_modules/@apidevtools/swagger-parser');
const root = path.join(__dirname, '..');
const tick = () => new Promise(resolve => setTimeout(resolve, 0));

(async () => {
  const spec = await Parser.validate(path.join(root, 'public/openapi.json'));
  assert.equal(Object.keys(spec.paths).length, 15);
  assert.equal(Object.values(spec.paths).reduce((n, operations) => n + Object.keys(operations).length, 0), 21);
  const apiFiles = fs.readdirSync(path.join(root, 'api')).filter(name => name.endsWith('.php'));
  assert.deepEqual(Object.keys(spec.paths).map(name => name.slice(1)).sort(), apiFiles.sort());
  console.log('OpenAPI schema valid; all 15 API files and 21 operations documented.');

  const dom = new JSDOM('<div id="notice" hidden></div><div id="orders"></div><p id="all-orders" hidden></p><button id="logout"></button>', {
    url: 'http://localhost:8085/public/orders.php', runScripts: 'outside-only',
  });
  const w = dom.window;
  let patchCount = 0;
  let rejectSave = false;
  let data = [{id:1,total:'49.99',created_at:'2026-10-28 14:00:00',status:'Processing',tracking_number:'DEMO123',whitelist_address_id:null,
    delivery_address:'123 Main Street, New York, NY 10001, USA',can_update_address:true,
    items:[{name:'<img src=x onerror=alert(1)>',quantity:1,price:'49.99'}],
    addresses_set_b:[{id:10,address_line:'456 Oak Avenue',city:'Los Angeles',state:'CA',zip:'90001',country:'USA'},
      {id:11,address_line:'789 Pine Road',city:'Chicago',state:'IL',zip:'60601',country:'USA'}]}];
  w.fetch = async (_url, options) => {
    if (options.method === 'GET') return {ok:true,status:200,json:async()=>({success:true,data:JSON.parse(JSON.stringify(data))})};
    if (options.method === 'PATCH') {
      patchCount++;
      if (rejectSave) return {ok:false,status:409,json:async()=>({success:false,message:'This order is no longer processing.'})};
      const payload = JSON.parse(options.body);
      assert.equal(payload.order_id, 1);
      const address = data[0].addresses_set_b.find(a => a.id === payload.whitelist_address_id);
      data[0].whitelist_address_id = address.id;
      data[0].delivery_address = [address.address_line,address.city,address.state,address.zip,address.country].join(', ');
      return {ok:true,status:200,json:async()=>({success:true})};
    }
    throw new Error('Unexpected request');
  };
  w.HTMLElement.prototype.scrollIntoView = () => {};
  w.eval(fs.readFileSync(path.join(root,'public/assets/orders.js'),'utf8'));
  await tick();
  const doc = w.document;
  assert.equal(doc.querySelectorAll('img').length, 0, 'Stored text was interpreted as HTML');
  assert.ok(doc.body.textContent.includes('Update delivery address'));
  assert.ok(!doc.body.textContent.includes('Want to change/update'));
  const clickEdit = () => doc.querySelector('[data-action="edit"]').click();
  const selectAddress = id => { const select = doc.querySelector('select'); select.value = String(id); select.dispatchEvent(new w.Event('change',{bubbles:true})); };
  clickEdit();
  assert.equal(doc.querySelector('form').hidden, false);
  assert.equal(doc.querySelector('[data-action="edit"]').getAttribute('aria-expanded'), 'true');
  assert.equal(doc.querySelector('[type="submit"]').disabled, true);
  selectAddress(10);
  assert.ok(doc.querySelector('.preview').textContent.includes('456 Oak Avenue, Los Angeles, CA, 90001, USA'));
  assert.equal(doc.querySelector('[type="submit"]').disabled, false);
  doc.querySelector('[data-action="close"]').click();
  assert.equal(doc.querySelector('form').hidden, true);
  assert.equal(patchCount, 0, 'Selection or Cancel saved the address');
  clickEdit(); selectAddress(10);
  doc.querySelector('form').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
  await tick();
  assert.equal(patchCount, 1);
  assert.ok(doc.querySelector('.address').textContent.includes('456 Oak Avenue'));
  assert.ok(doc.getElementById('notice').textContent.includes('Delivery address updated'));
  clickEdit();
  assert.equal(doc.querySelector('[type="submit"]').disabled, true, 'Unchanged address can be saved');
  selectAddress(11); rejectSave = true;
  doc.querySelector('form').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
  await tick();
  assert.equal(doc.querySelector('.form-error').hidden, false);
  assert.ok(doc.querySelector('.form-error').textContent.includes('no longer processing'));
  assert.equal(doc.querySelector('select').disabled, false);
  assert.equal(doc.querySelector('[type="submit"]').textContent, 'Save changes');
  data[0].can_update_address = false; data[0].status = 'Shipped';
  await w.fetchOrders();
  assert.equal(doc.querySelector('[data-action="edit"]'), null);
  data = []; await w.fetchOrders();
  assert.ok(doc.body.textContent.includes('No orders yet'));
  dom.window.close();
  console.log('Address editor DOM checks passed: preview, Cancel, explicit save, success, error recovery, dispatched orders, and escaping.');
})().catch(error => { console.error(error); process.exitCode = 1; });
