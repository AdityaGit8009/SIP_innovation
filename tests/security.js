'use strict';
const assert=require('assert');
const vm=require('vm');
const fs=require('fs');
let count=0;
const listeners={};
const token='test-csrf-token';
class FakeXHR {
    open(method,url) { this.method=method; this.url=url; }
    setRequestHeader(key,value) { (this.headers ||= {})[key]=value; }
    send(body) { this.body=body; }
}
const document={
    querySelector:()=>({content:token}),
    addEventListener:(name,fn)=>{listeners[name]=fn;},
    querySelectorAll:()=>[],
    createElement:()=>({}),
};
const window={fetch:(input,options)=>Promise.resolve({input,options})};
vm.runInNewContext(fs.readFileSync(__dirname+'/../assets/js/security.js','utf8'),{
    document,window,location:{href:'https://example.com/shop/cart.php',origin:'https://example.com'},
    URL,Request,Headers,XMLHttpRequest:FakeXHR
});
(async()=>{
    for (const [url,method,expected] of [
        ['/shop/login.php','POST',true],['/shop/get_counts.php','GET',false],
        ['https://other.example/endpoint','POST',false],['/shop/admin/products.php?delete=1','GET',true],
        ['/shop/remove_address.php?id=4','GET',true]
    ]) {
        const r=await window.fetch(url,{method});
        assert.equal(r.options.headers?.get('X-CSRF-Token')===token,expected);count++;
        const xhr=new FakeXHR();xhr.open(method,url);xhr.send('payload');
        assert.equal(xhr.headers?.['X-CSRF-Token']===token,expected);count++;
        assert.equal(xhr.body,'payload');count++;
    }
    const fields=[];
    const form={action:'https://example.com/shop/checkout.php',method:'post',querySelector:()=>null,appendChild:x=>fields.push(x)};
    listeners.submit({target:form});
    assert.equal(fields[0].name,'_csrf');assert.equal(fields[0].value,token);count+=2;
    const external={...form,action:'https://other.example/checkout'};listeners.submit({target:external});
    assert.equal(fields.length,1);count++;
    let appended;
    listeners.formdata({target:form,formData:{set:(key,value)=>{appended=[key,value];}}});
    assert.deepEqual(appended,['_csrf',token]);count++;
    console.log(`PASS: ${count} CSRF browser transport checks`);
})().catch(e=>{console.error(e);process.exit(1);});
