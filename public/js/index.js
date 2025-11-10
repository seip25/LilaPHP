document.addEventListener('DOMContentLoaded',async ()=>{
   const r = await fetch('/login',{
    method: 'POST',
    body : JSON.stringify({
        email: 'example@example.com',password:'1'
    }),
    headers:{
        "Content-Type": "application/json"
    }
   })
   if(!r.ok){
    const responseError=await r.json()
    document.getElementById("messages").innerHTML=responseError.html || "";
    return ;
    }
    const response=await r.json(); 
  
});