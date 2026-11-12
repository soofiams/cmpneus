(function(){
  // Leitor da medida do pneu
  var info = {
    w:"<b>205</b> é a largura do pneu em milímetros.",
    p:"<b>55</b> é a altura do flanco, em percentagem da largura (55% de 205 mm ≈ 113 mm).",
    r:"<b>R</b> quer dizer construção radial. É o tipo de quase todos os pneus de ligeiros.",
    d:"<b>16</b> é o diâmetro da jante em polegadas.",
    l:"<b>91</b> é o índice de carga: cada pneu aguenta até 615 kg.",
    s:"<b>V</b> é o índice de velocidade: o pneu está homologado até 240 km/h."
  };
  var code=document.getElementById("code"), ex=document.getElementById("explain");
  function pick(k){
    code.querySelectorAll("button").forEach(function(b){b.setAttribute("aria-pressed", b.dataset.k===k?"true":"false")});
    ex.innerHTML=info[k];
  }
  if(code){ code.addEventListener("click",function(e){var b=e.target.closest("button"); if(b) pick(b.dataset.k)}); pick("w"); }

  // Botões das revisões pré-selecionam o serviço
  document.querySelectorAll("[data-service]").forEach(function(a){
    a.addEventListener("click",function(){
      document.querySelectorAll("#q-svcs input").forEach(function(i){ if(i.value===a.dataset.service) i.checked=true; });
    });
  });

  // Data mínima = amanhã
  var d=new Date(); d.setDate(d.getDate()+1);
  var dateEl=document.getElementById("q-data");
  if(dateEl) dateEl.min=d.toISOString().slice(0,10);

  // Envio do pedido
  var form=document.getElementById("qform"), res=document.getElementById("result");
  function val(id){return document.getElementById(id).value.trim()}
  function setErr(id,msg){document.getElementById(id).textContent=msg||""}
  if(form) form.addEventListener("submit",function(e){
    e.preventDefault();
    var ok=true;
    ["e-nome","e-tel","e-svc","e-geral"].forEach(function(i){setErr(i)});
    if(!val("q-nome")){setErr("e-nome","Indique o seu nome.");ok=false}
    if(val("q-tel").replace(/\D/g,"").length<9){setErr("e-tel","Indique um número com 9 dígitos.");ok=false}
    if(!form.querySelector("#q-svcs input:checked")){setErr("e-svc","Escolha pelo menos um serviço.");ok=false}
    if(!ok) return;
    var btn=document.getElementById("q-submit");
    btn.disabled=true; btn.textContent="A enviar…";
    fetch(form.action,{method:"POST",body:new FormData(form),headers:{"Accept":"application/json"}})
      .then(function(r){return r.json().then(function(j){return {ok:r.ok,j:j}})})
      .then(function(x){
        if(!x.ok||!x.j.ok){ throw new Error(x.j&&x.j.erro||"Não foi possível enviar."); }
        form.querySelectorAll("input:not([type=hidden]):not([type=checkbox]),textarea").forEach(function(i){i.value=""});
        form.querySelectorAll("input[type=checkbox]").forEach(function(i){i.checked=false});
        res.hidden=false;
        res.scrollIntoView({behavior:"smooth",block:"nearest"});
      })
      .catch(function(err){ setErr("e-geral", err.message+" Tente novamente ou ligue-nos."); })
      .finally(function(){ btn.disabled=false; btn.textContent="Enviar pedido"; });
  });

  // Aberto agora? (hora de Portugal), a partir do horário definido no backoffice
  var el=document.getElementById("open-now");
  try{
    var parts=new Intl.DateTimeFormat("en-GB",{timeZone:"Europe/Lisbon",weekday:"short",hour:"2-digit",minute:"2-digit",hour12:false}).formatToParts(new Date());
    var get=function(t){return (parts.find(function(p){return p.type===t})||{}).value};
    var dia={Mon:1,Tue:2,Wed:3,Thu:4,Fri:5,Sat:6,Sun:7}[get("weekday")];
    var m=(parseInt(get("hour"),10)%24)*60+parseInt(get("minute"),10);
    var iv=(window.HORARIO&&window.HORARIO[dia])||[];
    var open=iv.some(function(i){return m>=i[0]&&m<i[1]});
    el.classList.toggle("on",open);
    el.lastElementChild.textContent=open?"Aberto agora":"Fechado agora";
  }catch(e){ if(el) el.hidden=true; }
})();

// ---------- Newsletter (secção e janela da promoção) ----------
(function(){
  document.querySelectorAll(".js-news").forEach(function(f){
    f.addEventListener("submit",function(e){
      e.preventDefault();
      var msg=f.querySelector(".news-msg"), btn=f.querySelector("button[type=submit]");
      msg.className="news-msg"; msg.textContent="";
      var email=f.querySelector("input[name=email]").value.trim();
      if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){ msg.className="news-msg err"; msg.textContent="Escreva um email válido."; return; }
      var c=f.querySelector("input[name=consentimento][type=checkbox]");
      if(c && !c.checked){ msg.className="news-msg err"; msg.textContent="Confirme que aceita receber as promoções."; return; }
      btn.disabled=true;
      fetch(f.action,{method:"POST",body:new FormData(f),headers:{"Accept":"application/json"}})
        .then(function(r){return r.json()})
        .then(function(j){
          msg.className="news-msg"+(j.ok?"":" err"); msg.textContent=j.msg;
          if(j.ok){ f.querySelector("input[name=email]").value=""; if(c) c.checked=false; try{localStorage.setItem("cm_news","1")}catch(_){} }
        })
        .catch(function(){ msg.className="news-msg err"; msg.textContent="Não foi possível subscrever. Tente mais tarde."; })
        .finally(function(){ btn.disabled=false; });
    });
  });
})();

// ---------- Janela da promoção do mês ----------
(function(){
  var p=document.getElementById("promo"), tab=document.getElementById("promo-tab");
  if(!p) return;
  var chave="cm_promo_"+p.dataset.id, ultimoFoco=null;
  function visto(){ try{return localStorage.getItem(chave)==="1"}catch(_){return false} }
  function marcar(){ try{localStorage.setItem(chave,"1")}catch(_){} }
  function abrir(){
    ultimoFoco=document.activeElement;
    p.hidden=false; tab.hidden=true;
    document.body.style.overflow="hidden";
    p.querySelector(".promo-x").focus();
  }
  function fechar(){
    p.hidden=true; tab.hidden=false; marcar();
    document.body.style.overflow="";
    if(ultimoFoco && ultimoFoco.focus) ultimoFoco.focus();
  }
  p.addEventListener("click",function(e){
    var el=e.target.closest("[data-fechar]");
    if(!el) return;
    if(el.dataset.promo){
      // Pré-preenche o pedido de orçamento com a promoção
      var msg=document.getElementById("q-msg");
      if(msg && msg.value.indexOf(el.dataset.promo)<0) msg.value=("Promoção: "+el.dataset.promo+"\n"+msg.value).trim();
    }
    fechar();
  });
  document.addEventListener("keydown",function(e){ if(e.key==="Escape" && !p.hidden) fechar(); });
  tab.addEventListener("click",abrir);
  if(/[?&]verpromo/.test(location.search)){ abrir(); }
  else if(visto()){ tab.hidden=false; }
  else { setTimeout(function(){ if(p.hidden) abrir(); }, Math.max(0,parseInt(p.dataset.atraso,10)||0)*1000); }
})();
