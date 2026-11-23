document.getElementById('authForm').addEventListener('submit',async event=>{
 event.preventDefault();const form=event.target;if(!form.reportValidity())return;
 const button=form.querySelector('button');if(button.disabled)return;button.disabled=true;const label=button.textContent;button.textContent='Please wait…';
 const payload=Object.fromEntries(new FormData(form));if(form.dataset.endpoint==='auth/reset-password')payload.token=new URLSearchParams(location.search).get('token') || '';
 const notice=document.getElementById('authNotice');notice.hidden=false;
 try{const result=await StoreUI.request(form.dataset.endpoint,{method:'POST',body:payload});
  if(form.dataset.endpoint==='auth/login'){const requested=new URLSearchParams(location.search).get('redirect');let target='/shop';if(requested?.startsWith('/')&&!requested.startsWith('//')){const url=new URL(requested,location.origin);if(url.origin===location.origin&&/^\/(shop|cart|checkout|orders|account|products\/(detail|[0-9]+))$/.test(url.pathname))target=url.pathname+url.search;}location.href=target;return;}
  notice.className='notice';notice.textContent=result.message;form.reset();
 }catch(error){notice.className='notice error';notice.textContent=error.message;}finally{button.disabled=false;button.textContent=label;}
});
