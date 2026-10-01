<?php
require __DIR__ . '/config.php';
function out($x) { echo json_encode($x, JSON_UNESCAPED_UNICODE); exit; }
function gs($in) {
    $in['token'] = SCRIPT_TOKEN;
    $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => SSL_VERIFY, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_ENCODING => '', CURLOPT_TCP_NODELAY => true,
          CURLOPT_HTTP_VERSION => defined('CURL_HTTP_VERSION_2TLS') ? CURL_HTTP_VERSION_2TLS : CURL_HTTP_VERSION_1_1];
    $c = curl_init(SCRIPT_URL);
    curl_setopt_array($c, $o + [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($in, JSON_UNESCAPED_UNICODE), CURLOPT_HTTPHEADER => ['Content-Type: text/plain']]);
    $r = curl_exec($c);
    $n = curl_getinfo($c, CURLINFO_REDIRECT_URL);
    if ($r !== false && $n) { $c = curl_init($n); curl_setopt_array($c, $o); $r = curl_exec($c); }
    if ($r === false) return ['ok' => false, 'error' => 'Không kết nối được Google Sheet: ' . curl_error($c)];
    $j = json_decode($r, true);
    return $j ?: ['ok' => false, 'error' => 'Google không trả về JSON (kiểm tra SCRIPT_URL và quyền "Bất kỳ ai")'];
}
if (isset($_GET['api'])) {
    session_start();
    header('Content-Type: application/json; charset=utf-8');
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $a = $in['action'] ?? '';
    $adm = ['kho_save', 'kho_del', 'update', 'del', 'users', 'user_save', 'user_del'];
    if ($a == 'me') out(['ok' => true, 'data' => $_SESSION['u'] ?? null]);
    if ($a == 'logout') { session_destroy(); out(['ok' => true]); }
    if ($a == 'login') {
        $r = gs(['action' => 'login', 'user' => $in['user'] ?? '', 'pass' => $in['pass'] ?? '']);
        if ($r['ok']) { session_regenerate_id(true); $_SESSION['u'] = $r['data']; }
        out($r);
    }
    $u = $_SESSION['u'] ?? null; session_write_close();
    if (!$u) out(['ok' => false, 'auth' => 1, 'error' => 'Hết phiên đăng nhập, vui lòng đăng nhập lại']);
    if (!in_array($a, array_merge($adm, ['list', 'add', 'move']))) out(['ok' => false, 'error' => 'Hành động không hợp lệ']);
    if (in_array($a, $adm) && $u['role'] != 'admin') out(['ok' => false, 'error' => 'Bạn không có quyền thực hiện thao tác này']);
    if ($a == 'user_del' && ($in['user'] ?? '') == $u['user']) out(['ok' => false, 'error' => 'Không thể xóa chính tài khoản đang đăng nhập']);
    $in['nv'] = $u['ten'];
    out(gs($in));
}
?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Quản lý kho xe cầm đồ</title>
<style>
:root{--ink:#1d2327;--paper:#f3f4f1;--line:#e1e4de;--acc:#e8452c;--ok:#1f8a5b;--warn:#b86e00;--mut:#6b7570}
*{box-sizing:border-box}
body{margin:0;font:14px/1.5 "Segoe UI",system-ui,sans-serif;background:var(--paper);color:var(--ink)}
[hidden]{display:none!important}
#app{display:flex;min-height:100vh}
aside{width:236px;background:var(--ink);color:#d7ddd9;padding:22px 16px;display:flex;flex-direction:column;gap:6px;position:sticky;top:0;height:100vh}
.logo{font-size:18px;font-weight:700;color:#fff;margin:0 8px 18px}.logo b{color:var(--acc)}
.nav{background:none;border:0;color:inherit;text-align:left;padding:10px 12px;border-radius:8px;font:inherit;cursor:pointer}
.nav.on,.nav:hover{background:#2c3439;color:#fff}
.who{margin-top:auto;font-size:12px;color:#9aa5a0;padding:0 8px}.who b{display:block;color:#fff;font-size:14px}
.who button{margin-top:8px;background:#2c3439;color:#fff;border:0;border-radius:6px;padding:6px 12px;cursor:pointer}
main{flex:1;padding:28px 32px;min-width:0}
h1{margin:0 0 18px;font-size:22px}
.stats{display:flex;background:#fff;border:1px solid var(--line);border-radius:12px;margin-bottom:18px}
.stats div{flex:1;padding:14px 18px;border-right:1px solid var(--line)}.stats div:last-child{border:0}
.stats b{display:block;font-size:26px;font-variant-numeric:tabular-nums}.stats span{color:var(--mut);font-size:12px}
.bar{display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap}.bar input{flex:1;min-width:200px}
input,select,textarea{font:inherit;border:1px solid var(--line);border-radius:8px;padding:9px 12px;background:#fff;color:inherit;width:100%}
.bar input,.bar select{width:auto}
input:focus,select:focus,textarea:focus,.btn:focus-visible{outline:2px solid var(--acc);outline-offset:-1px}
.btn{border:0;border-radius:8px;padding:9px 16px;font:inherit;font-weight:600;cursor:pointer;background:var(--acc);color:#fff}
.btn:disabled{opacity:.6;cursor:wait}
.btn.s{background:#fff;color:var(--ink);border:1px solid var(--line);padding:5px 10px;font-weight:500}.btn.d{color:#c0301c}
.wrap{background:#fff;border:1px solid var(--line);border-radius:12px;overflow:auto}
table{width:100%;border-collapse:collapse;white-space:nowrap}
th{text-align:left;font-weight:600;color:var(--mut);font-size:12px;padding:11px 14px;border-bottom:1px solid var(--line);background:#fafbf9}
td{padding:11px 14px;border-bottom:1px solid #eef0ec}tr:last-child td{border:0}
.tag{padding:3px 10px;border-radius:99px;font-size:12px;font-weight:600}
.in{background:#e3f4eb;color:var(--ok)}.out{background:#fdf0dc;color:var(--warn)}.mv{background:#e6eefb;color:#2557b8}
.act{display:flex;gap:6px}.empty{padding:40px;text-align:center;color:var(--mut)}
.ov{position:fixed;inset:0;background:#1d232799;display:flex;align-items:center;justify-content:center;padding:16px;z-index:5}
.md{background:#fff;border-radius:14px;padding:24px;width:520px;max-width:100%;max-height:90vh;overflow:auto}
.md h2{margin:0 0 14px;font-size:18px}.md p{margin:0 0 12px;color:var(--mut)}
.g{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.g label{display:flex;flex-direction:column;gap:4px;font-size:12px;color:var(--mut)}.g .w{grid-column:1/-1}
.f{display:flex;justify-content:flex-end;gap:8px;margin-top:18px}
#t{position:fixed;bottom:20px;right:20px;background:var(--ink);color:#fff;padding:12px 18px;border-radius:10px;display:none;z-index:9}#t.e{background:#c0301c}
#login{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px}
#login form{background:#fff;border:1px solid var(--line);border-radius:14px;padding:32px;width:360px;max-width:100%;display:flex;flex-direction:column;gap:12px}
#login h2{margin:0 0 4px;font-size:22px}#login h2 b{color:var(--acc)}#login p{margin:0 0 6px;color:var(--mut)}
#le{color:#c0301c;min-height:20px;font-size:13px}
@media(max-width:800px){#app{flex-direction:column}aside{width:auto;height:auto;position:static;flex-direction:row;flex-wrap:wrap;align-items:center}.who{margin:0;width:100%}main{padding:18px}.stats{flex-wrap:wrap}.stats div{flex:1 1 50%}}
</style>
</head>
<body>
<div id="login" hidden>
  <form id="lf">
    <h2>Kho xe <b>cầm đồ</b></h2><p>Đăng nhập để tiếp tục</p>
    <input id="lu" placeholder="Tên đăng nhập" autocomplete="username" required>
    <input id="lp" type="password" placeholder="Mật khẩu" autocomplete="current-password" required>
    <div id="le"></div>
    <button class="btn" id="lb">Đăng nhập</button>
  </form>
</div>
<div id="app" hidden>
  <aside>
    <div class="logo">Kho xe <b>cầm đồ</b></div>
    <div id="nav" style="display:contents"></div>
    <div class="who"><b id="wn"></b><span id="wr"></span><br><button id="lo">Đăng xuất</button></div>
  </aside>
  <main>
    <h1 id="title"></h1>
    <div class="stats" id="stats"></div>
    <div class="bar">
      <input id="q" placeholder="Tìm kiếm...">
      <select id="fk"></select>
      <select id="st"><option value="">Tất cả trạng thái</option><option>Trong kho</option><option>Đã xuất</option></select>
      <button class="btn" id="add"></button>
    </div>
    <div class="wrap" id="tb"></div>
  </main>
</div>
<div id="t"></div>
<script>
const $=s=>document.querySelector(s);
const e=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let S={kho:[],xe:[],ls:[]},U=[],me=null,V='xe';
const ADM=()=>me&&me.role=='admin';
const save=()=>{try{localStorage.setItem('kx_'+me.user,JSON.stringify(S))}catch(x){}};
const nowS=()=>{const d=new Date(),p=n=>String(n).padStart(2,'0');return `${p(d.getDate())}/${p(d.getMonth()+1)}/${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`};
const kn=id=>(S.kho.find(k=>k.id==id)||{}).ten||id;
const toast=(m,x)=>{const t=$('#t');t.textContent=m;t.className=x?'e':'';t.style.display='block';setTimeout(()=>t.style.display='none',3500)};
async function api(a,p={}){
  const r=await fetch('?api=1',{method:'POST',body:JSON.stringify({action:a,...p})});
  const j=await r.json();
  if(!j.ok){if(j.auth)showLogin();throw new Error(j.error)}
  return j.data}
function showLogin(){me=null;$('#app').hidden=true;$('#login').hidden=false;closeM();$('#lp').value=''}
async function start(){
  try{me=await api('me')}catch(x){me=null}
  if(!me)return showLogin();
  $('#login').hidden=true;$('#app').hidden=false;
  $('#wn').textContent=me.ten;$('#wr').textContent=ADM()?'Quản trị viên':'Nhân viên';
  const tabs=[['xe','Xe trong kho'],['kho','Quản lý kho',1],['ls','Lịch sử nhập/xuất'],['nv','Nhân viên',1]].filter(t=>!t[2]||ADM());
  $('#nav').innerHTML=tabs.map(t=>`<button class="nav" data-v="${t[0]}">${t[1]}</button>`).join('');
  document.querySelectorAll('.nav').forEach(b=>b.onclick=()=>go(b.dataset.v));
  V='xe';let c=localStorage.getItem('kx_'+me.user);
  if(c){try{S=JSON.parse(c)}catch(x){c=0}}
  if(c){go('xe');await load();draw()}else{$('#tb').innerHTML='<div class="empty">Đang tải dữ liệu...</div>';await load();go('xe')}}
async function load(){
  try{S=await api('list');save()}
  catch(x){toast('Không tải được dữ liệu: '+x.message,1);if(!S.xe.length)$('#tb').innerHTML='<div class="empty">Không tải được dữ liệu: '+e(x.message)+'</div>'}}
async function go(v){
  V=v;document.querySelectorAll('.nav').forEach(b=>b.classList.toggle('on',b.dataset.v==v));
  if(v=='nv'&&!U.length){try{U=await api('users')}catch(x){toast(x.message,1)}}
  $('#q').value='';draw()}
async function run(a,p,msg){
  const b=$('#ok');if(b){b.disabled=true;b.textContent='Đang lưu...'}
  try{const d=await api(a,p);a.startsWith('user')?U=d:(S=d,save());toast(msg);closeM();draw();if(!a.startsWith('user'))load().then(draw)}
  catch(x){toast(x.message,1);if(b){b.disabled=false;b.textContent='Thử lại'}}}

const T=(h,r,m)=>r.length?`<table><tr>${h.map(x=>`<th>${x}</th>`).join('')}</tr>${r.join('')}</table>`:`<div class="empty">${m}</div>`;
const tr=c=>`<tr>${c.map(x=>`<td>${x}</td>`).join('')}</tr>`;
const tag=(t,c)=>`<span class="tag ${c}">${e(t)}</span>`;

function draw(){
  const q=$('#q').value.toLowerCase(),hit=a=>!q||a.join(' ').toLowerCase().includes(q);
  const inN=S.xe.filter(x=>x.trangthai=='Trong kho').length,today=new Date().toLocaleDateString('en-GB');
  $('#stats').innerHTML=`<div><b>${S.xe.length}</b><span>Tổng số xe</span></div><div><b>${inN}</b><span>Đang trong kho</span></div><div><b>${S.xe.length-inN}</b><span>Đã xuất</span></div><div><b>${S.kho.length}</b><span>Số kho</span></div><div><b>${S.ls.filter(l=>l[0].startsWith(today)).length}</b><span>Giao dịch hôm nay</span></div>`;
  const fk=$('#fk'),cur=fk.value;
  fk.innerHTML='<option value="">Tất cả kho</option>'+S.kho.map(k=>`<option value="${e(k.id)}">${e(k.ten)}</option>`).join('');fk.value=cur;
  $('#title').textContent={xe:'Xe trong kho',kho:'Quản lý kho',ls:'Lịch sử nhập/xuất',nv:'Quản lý nhân viên'}[V];
  $('#fk').hidden=$('#st').hidden=V!='xe';
  $('#add').hidden=V=='ls';$('#add').textContent={xe:'Nhập xe mới',kho:'Thêm kho',nv:'Thêm nhân viên'}[V]||'';
  let h;
  if(V=='xe'){
    const st=$('#st').value,k=fk.value;
    const rows=S.xe.filter(x=>hit([x.hopdong,x.khach,x.tenxe,x.bienso,kn(x.kho),x.ghichu])&&(!st||x.trangthai==st)&&(!k||x.kho==k)).reverse().slice(0,300).map(x=>{
      const o=x.trangthai=='Trong kho';
      return tr([e(x.hopdong),e(x.khach),e(x.tenxe),e(x.bienso),e(kn(x.kho)),tag(x.trangthai,o?'in':'out'),e(x.gionhap),e(x.gioxuat),
      `<div class="act"><button class="btn s" onclick="mv('${x.id}','${o?'XUẤT':'NHẬP'}')">${o?'Xuất kho':'Nhập lại'}</button>${ADM()?`<button class="btn s" onclick="ex('${x.id}')">Sửa</button><button class="btn s d" onclick="dx('${x.id}')">Xóa</button>`:''}</div>`])});
    h=T(['Mã hợp đồng','Khách hàng','Tên xe','Biển số','Kho','Trạng thái','Giờ nhập','Giờ xuất',''],rows,S.xe.length?'Không có xe nào khớp bộ lọc.':'Chưa có xe nào. Bấm "Nhập xe mới" để bắt đầu.');
  }else if(V=='kho'){
    h=T(['Mã','Tên kho','Địa chỉ','Ghi chú','Xe đang giữ',''],S.kho.filter(k=>hit([k.ten,k.diachi,k.ghichu])).map(k=>tr([e(k.id),e(k.ten),e(k.diachi),e(k.ghichu),S.xe.filter(x=>x.kho==k.id&&x.trangthai=='Trong kho').length,
      `<div class="act"><button class="btn s" onclick="ek('${k.id}')">Sửa</button><button class="btn s d" onclick="dk('${k.id}')">Xóa</button></div>`])),'Chưa có kho nào.');
  }else if(V=='ls'){
    h=T(['Thời gian','Loại','Mã hợp đồng','Khách hàng','Tên xe','Biển số','Kho','Nhân viên','Ghi chú'],S.ls.filter(hit).slice(0,300).map(l=>tr([e(l[0]),tag(l[1],l[1]=='NHẬP'?'in':l[1]=='XUẤT'?'out':'mv'),...[3,4,5,6,7,8,9].map(i=>e(l[i]))])),'Chưa có giao dịch nào.');
  }else{
    h=T(['Tên đăng nhập','Họ tên','Vai trò','Trạng thái',''],U.map((u,i)=>[u,i]).filter(([u])=>hit([u.user,u.ten])).map(([u,i])=>tr([e(u.user),e(u.ten),u.role=='admin'?'Quản trị viên':'Nhân viên',tag(u.active=='Có'?'Hoạt động':'Đã khóa',u.active=='Có'?'in':'out'),
      `<div class="act"><button class="btn s" onclick="eu(${i})">Sửa</button><button class="btn s d" onclick="du(${i})">Xóa</button></div>`])),'Chưa có nhân viên.');
  }
  $('#tb').innerHTML=h}

function modal(t,body,ok,label){
  closeM();const o=document.createElement('div');o.className='ov';o.id='m';
  o.innerHTML=`<div class="md"><h2>${t}</h2>${body}<div class="f"><button class="btn s" onclick="closeM()">Hủy</button><button class="btn" id="ok">${label}</button></div></div>`;
  document.body.append(o);$('#ok').onclick=ok;const i=o.querySelector('input,select');i&&i.focus()}
const closeM=()=>$('#m')?.remove();
const val=k=>$('#f_'+k).value.trim();
function form(F,o={}){
  return '<div class="g">'+F.map(([k,l,t,opt])=>{
    const v=e(o[k]),w=t=='ta'?' class="w"':'';
    if(t=='sel')return `<label${w}>${l}<select id="f_${k}">${(opt||[]).map(([a,b])=>`<option value="${e(a)}"${o[k]==a?' selected':''}>${e(b)}</option>`).join('')}</select></label>`;
    if(t=='ta')return `<label class="w">${l}<textarea id="f_${k}" rows="2">${v}</textarea></label>`;
    return `<label>${l}<input id="f_${k}" value="${v}"${t=='ro'?' readonly':''}></label>`}).join('')+'</div>'}
function collect(F){
  const o={};
  for(const [k,l] of F){o[k]=val(k);if(l.includes('*')&&!o[k]){toast('Vui lòng nhập: '+l.replace(' *',''),1);return null}}
  return o}
const FX=()=>[['hopdong','Mã hợp đồng *'],['khach','Tên khách *'],['tenxe','Tên xe *'],['bienso','Biển số'],['kho','Kho đang để xe *','sel',S.kho.map(k=>[k.id,k.ten])],['ghichu','Ghi chú','ta']];
const FK=[['ten','Tên kho *'],['diachi','Địa chỉ'],['ghichu','Ghi chú','ta']];
const FU=(n)=>[['user','Tên đăng nhập *',n?'ro':''],['ten','Họ tên *'],['role','Vai trò','sel',[['nhanvien','Nhân viên'],['admin','Quản trị viên']]],['active','Hoạt động','sel',[['Có','Hoạt động'],['Không','Khóa tài khoản']]],['pass',n?'Mật khẩu mới (bỏ trống nếu không đổi)':'Mật khẩu *']];

$('#add').onclick=()=>{
  if(V=='xe'){if(!S.kho.length)return toast('Chưa có kho nào, hãy nhờ quản trị viên tạo kho trước',1);
    const F=FX();modal('Nhập xe mới',form(F),()=>{const o=collect(F);o&&run('add',{xe:o},'Đã nhập xe vào kho')},'Nhập kho')}
  else if(V=='kho')modal('Thêm kho',form(FK),()=>{const o=collect(FK);o&&run('kho_save',{kho:o},'Đã thêm kho')},'Thêm kho');
  else{const F=FU(0);modal('Thêm nhân viên',form(F),()=>{const o=collect(F);o&&run('user_save',{u:o},'Đã thêm tài khoản')},'Thêm tài khoản')}};
function ex(id){const F=FX(),x=S.xe.find(v=>v.id==id);modal('Sửa thông tin xe',form(F,x),()=>{const o=collect(F);if(o){o.id=id;run('update',{xe:o},'Đã lưu thay đổi')}},'Lưu thay đổi')}
function dx(id){if(confirm('Xóa xe này khỏi hệ thống? Lịch sử cũ vẫn được giữ.'))run('del',{id},'Đã xóa xe')}
function mv(id,loai){
  const x=S.xe.find(v=>v.id==id),o=loai=='XUẤT';
  modal(o?'Xuất kho':'Nhập lại kho',`<p>${e(x.tenxe)} - ${e(x.bienso)} - HĐ ${e(x.hopdong)}</p><div class="g"><label class="w">${o?'Người nhận / lý do xuất':'Ghi chú'}<input id="f_gc"></label></div>`,
    ()=>{const gc=val('gc'),t=nowS();x.trangthai=o?'Đã xuất':'Trong kho';if(o)x.gioxuat=t;else{x.gionhap=t;x.gioxuat=''}closeM();draw();
      api('move',{id,loai,ghichu:gc}).then(d=>{S=d;save();draw();toast(o?'Đã xuất kho':'Đã nhập lại kho')}).catch(z=>{toast(z.message,1);load().then(draw)})},o?'Xác nhận xuất':'Xác nhận nhập')}
function ek(id){const k=S.kho.find(v=>v.id==id);modal('Sửa kho',form(FK,k),()=>{const o=collect(FK);if(o){o.id=id;run('kho_save',{kho:o},'Đã lưu kho')}},'Lưu thay đổi')}
function dk(id){if(confirm('Xóa kho này?'))run('kho_del',{id},'Đã xóa kho')}
function eu(i){const F=FU(1);modal('Sửa tài khoản',form(F,U[i]),()=>{const o=collect(F);o&&run('user_save',{u:o},'Đã lưu tài khoản')},'Lưu thay đổi')}
function du(i){if(confirm('Xóa tài khoản '+U[i].user+'?'))run('user_del',{user:U[i].user},'Đã xóa tài khoản')}

let tm;$('#q').oninput=()=>{clearTimeout(tm);tm=setTimeout(draw,150)};$('#st').onchange=$('#fk').onchange=draw;
$('#lf').onsubmit=async ev=>{
  ev.preventDefault();const b=$('#lb');b.disabled=true;$('#le').textContent='';
  try{await api('login',{user:$('#lu').value,pass:$('#lp').value});await start()}
  catch(x){$('#le').textContent=x.message}
  b.disabled=false};
$('#lo').onclick=async()=>{try{localStorage.removeItem('kx_'+me.user)}catch(x){}await api('logout').catch(()=>{});showLogin()};
start();
</script>
</body>
</html>