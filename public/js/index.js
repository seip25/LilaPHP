document.addEventListener('DOMContentLoaded',async ()=>{
   const r = await fetch('/login',{
    method: 'POST',
    body : JSON.stringify({
        email: '1',password:'1'
    })
   })
   if(!r.ok){
    console.error(await r.json())
    console.log(r)
    return ;
    }
    const response=await r.json();
    console.log(response)
});