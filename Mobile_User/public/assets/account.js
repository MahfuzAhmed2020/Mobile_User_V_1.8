(() => {
    const {request,refreshCart} = StoreUI;
    let profile = null, mode = null, pending = false;
    const profileForm = document.getElementById('profileForm');
    const passwordForm = document.getElementById('passwordForm');
    const editProfile = document.getElementById('editProfile');
    const editPassword = document.getElementById('editPassword');
    function feedback(id, message, error = false) {
        const target = document.getElementById(id); target.textContent = message; target.className = 'form-feedback' + (error ? ' error' : ''); target.hidden = false;
    }
    function renderProfile() {
        profileForm.elements.first_name.value = profile.first_name;
        profileForm.elements.last_name.value = profile.last_name;
        profileForm.elements.email.value = profile.email;
        document.getElementById('summaryFirstName').textContent = profile.first_name;
        document.getElementById('summaryLastName').textContent = profile.last_name;
        document.getElementById('summaryEmail').textContent = profile.email;
        document.getElementById('accountEmail').textContent = profile.email;
        document.querySelectorAll('[data-user-name]').forEach(element => { element.textContent = profile.first_name + ' ' + profile.last_name; });
        document.querySelectorAll('[data-user-initials]').forEach(element => { element.textContent = (profile.first_name.slice(0,1) + profile.last_name.slice(0,1)).toUpperCase(); });
    }
    function dirty() {
        if (!profile) return false;
        if (mode === 'profile') return ['first_name','last_name','email'].some(key => profileForm.elements[key].value.trim() !== profile[key]);
        if (mode === 'password') return [...passwordForm.querySelectorAll('input')].some(input => input.value !== '');
        return false;
    }
    function updateControls() {
        editProfile.hidden = mode === 'profile'; editPassword.hidden = mode === 'password';
        editProfile.disabled = !profile || pending; editPassword.disabled = !profile || pending;
        editProfile.setAttribute('aria-expanded', String(mode === 'profile'));
        editPassword.setAttribute('aria-expanded', String(mode === 'password'));
        profileForm.hidden = mode !== 'profile'; passwordForm.hidden = mode !== 'password';
        document.getElementById('profileSummary').hidden = mode === 'profile';
        document.getElementById('passwordSummary').hidden = mode === 'password';
        document.getElementById('profileFields').disabled = mode !== 'profile' || pending;
        document.getElementById('passwordFields').disabled = mode !== 'password' || pending;
        profileForm.querySelector('[type="submit"]').disabled = pending || !dirty();
        passwordForm.querySelector('[type="submit"]').disabled = pending;
        profileForm.querySelector('[type="submit"]').textContent = pending && mode === 'profile' ? 'Saving…' : 'Save changes';
        passwordForm.querySelector('[type="submit"]').textContent = pending && mode === 'password' ? 'Updating…' : 'Update password';
    }
    async function changeMode(next) {
        if (!profile || pending) return;
        if (dirty()) {
            const discard = await StoreUI.confirm({title:'Discard unsaved changes?',message:'Your changes have not been saved. You can keep editing or discard them and continue.',label:'Discard changes',cancelLabel:'Keep editing',danger:true});
            if (!discard) return;
        }
        const previous = mode;
        renderProfile(); passwordForm.reset();
        document.getElementById('profileFeedback').hidden = true;
        document.getElementById('passwordFeedback').hidden = true;
        mode = next; updateControls();
        if (next === 'profile') profileForm.elements.first_name.focus();
        else if (next === 'password') passwordForm.elements.old_password.focus();
        else (previous === 'password' ? editPassword : editProfile).focus();
    }
    editProfile.addEventListener('click', () => changeMode('profile'));
    editPassword.addEventListener('click', () => changeMode('password'));
    document.getElementById('resetProfile').addEventListener('click', () => changeMode(null));
    document.getElementById('cancelPassword').addEventListener('click', () => changeMode(null));
    profileForm.addEventListener('input', updateControls);
    window.addEventListener('beforeunload', event => { if (dirty()) { event.preventDefault(); event.returnValue = ''; } });
    profileForm.addEventListener('submit', async event => {
        event.preventDefault(); if (mode !== 'profile' || pending || !dirty() || !profileForm.reportValidity()) return;
        const payload = Object.fromEntries([...new FormData(profileForm)].map(([key,value]) => [key,value.trim()]));
        pending = true; updateControls();
        try {
            await request('update_profile_api.php',{method:'POST',body:payload});
            profile = {...profile,...payload}; renderProfile(); mode = null;
            feedback('profileFeedback','Your personal details have been updated.');
        } catch (error) { feedback('profileFeedback',error.message,true); }
        finally { pending = false; updateControls(); if (!mode) editProfile.focus(); }
    });
    passwordForm.addEventListener('submit', async event => {
        event.preventDefault(); if (mode !== 'password' || pending || !passwordForm.reportValidity()) return;
        const payload = Object.fromEntries(new FormData(passwordForm));
        if (payload.new_password !== payload.confirm_password) { feedback('passwordFeedback','Your new passwords do not match.',true); return; }
        if (!/(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}/.test(payload.new_password)) { feedback('passwordFeedback','Use at least 8 characters, an uppercase letter, a number, and a special character.',true); return; }
        pending = true; updateControls();
        try {
            await request('update_profile_api.php',{method:'POST',body:{first_name:profile.first_name,last_name:profile.last_name,email:profile.email,...payload}});
            passwordForm.reset(); mode = null; feedback('passwordFeedback','Your password has been updated.');
        } catch (error) { feedback('passwordFeedback',error.message,true); }
        finally { pending = false; updateControls(); if (!mode) editPassword.focus(); }
    });
    request('profile_api.php').then(result => { profile = result.data; renderProfile(); updateControls(); }).catch(error => {
        const notice = document.getElementById('pageNotice'); notice.textContent = error.message; notice.hidden = false; notice.className = 'notice error';
    });
    refreshCart();
})();