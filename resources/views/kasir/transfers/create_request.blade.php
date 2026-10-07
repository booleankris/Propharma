@extends('layouts.app')

@section('title', 'Permintaan Mutasi')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        #sourcePharmacy, #medicineSearch, #stageQty { font-size: .875rem !important; }
        #sourcePharmacy { min-height: 42px; }
    .request-search-option.active, .request-search-option[aria-selected="true"] { background: #eef2ff; }
    </style>
@endsection

@section('content')
    <div class="pb-5 md:pb-4 px-3 sm:px-6">
        <div class="flex flex-col my-2 gap-4 p-4 bg-white border border-slate-200/80 rounded-xl shadow-sm md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 shrink-0">
                    <svg class="w-5 h-5 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 12h15M13 5l7 7-7 7"/><path d="M4 5v14" opacity=".45"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-base font-bold text-slate-800 leading-tight">Permintaan Mutasi</h1>
                        <span class="inline-flex items-center rounded-md bg-indigo-50 border border-indigo-100 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-indigo-700">Permintaan</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5">Ajukan kebutuhan stok ke cabang pengirim</p>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full md:w-auto">
                <div class="grid grid-cols-2 gap-2">
                    <div class="bg-indigo-600 border border-indigo-700 px-3 py-1.5 rounded-lg flex flex-col justify-center min-w-[132px]">
                        <span class="text-[9px] font-semibold text-indigo-100 uppercase tracking-wider">Kode Permintaan</span>
                        <span class="font-mono text-xs font-bold text-white truncate">{{ $code }}</span>
                    </div>
                    <div class="bg-indigo-600 border border-indigo-700 px-3 py-1.5 rounded-lg flex flex-col justify-center min-w-[132px]">
                        <span class="text-[9px] font-semibold text-indigo-100 uppercase tracking-wider">Tanggal</span>
                        <span class="text-xs font-semibold text-white truncate">{{ $now ?? now()->format('d M Y, H:i') }}</span>
                    </div>
                </div>
                <a href="{{ route('transfers.incoming') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-slate-600 bg-white hover:bg-slate-50 border border-slate-200 rounded-lg transition">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    <span>Lihat Mutasi</span>
                </a>
            </div>
        </div>
    </div>

    <div class="text-slate-800 px-3 sm:px-6 pb-8">
        @if ($errors->any())
            <div class="mb-4 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-medium">{{ $errors->first() }}</div>
        @endif

        <div class="mb-5 flex flex-col sm:flex-row sm:items-center gap-3 px-4 py-3.5 rounded-xl border border-amber-200 bg-amber-50/70">
            <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-100 text-amber-700 shrink-0">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 2.6 17.2A2 2 0 0 0 4.3 20h15.4a2 2 0 0 0 1.7-2.8L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
            </div>
            <div>
                <p class="text-xs font-bold text-amber-900">Stok belum berubah</p>
                <p class="text-[11px] text-amber-800/80 mt-0.5">Stok pengirim baru dikurangi setelah cabang pengirim menyetujui permintaan ini.</p>
            </div>
            <div class="sm:ml-auto flex items-center gap-1.5 text-[10px] font-semibold text-amber-800 whitespace-nowrap">
                <span class="w-5 h-5 rounded-full bg-white border border-amber-200 grid place-items-center">1</span><span>Ajukan</span>
                <span class="text-amber-400 px-1">›</span>
                <span class="w-5 h-5 rounded-full bg-white border border-amber-200 grid place-items-center">2</span><span>Disetujui</span>
                <span class="text-amber-400 px-1">›</span>
                <span class="w-5 h-5 rounded-full bg-white border border-amber-200 grid place-items-center">3</span><span>Diterima</span>
            </div>
        </div>

        <form id="requestForm" method="POST" action="{{ route('transfers.requests.store') }}" class="space-y-5">
            @csrf
            <section class="bg-white rounded-xl border border-slate-200/80 shadow-sm p-5 sm:p-6">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-6 h-6 rounded-md bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold text-xs">1</div>
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">Pilih Cabang Pengirim</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">Cabang yang akan menerima dan memproses permintaan</p>
                    </div>
                </div>
                <select name="source_pharmacy_id" id="sourcePharmacy" required class="w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3.5 py-2.5 text-sm text-slate-800 outline-none transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                    <option value="">— Pilih cabang pengirim —</option>
                    @foreach ($pharmacies as $pharmacy)
                        <option value="{{ $pharmacy->id }}" @selected(old('source_pharmacy_id') == $pharmacy->id)>{{ $pharmacy->name }}</option>
                    @endforeach
                </select>
            </section>

            <section class="bg-white rounded-xl border border-slate-200/80 shadow-sm p-5 sm:p-6">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-6 h-6 rounded-md bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold text-xs">2</div>
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">Cari Obat yang Dibutuhkan</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">Pilih obat dan jumlah permintaan</p>
                    </div>
                </div>

                <div class="relative w-full" id="searchWrapper">
                    <div class="relative">
                        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6.5" cy="6.5" r="4.5"/><line x1="10.5" y1="10.5" x2="14" y2="14"/></svg>
                <input type="search" id="medicineSearch" autocomplete="off" disabled aria-autocomplete="list" aria-controls="searchResultRows" aria-expanded="false" placeholder="Pilih cabang pengirim terlebih dahulu" class="w-full pl-10 pr-4 py-3 sm:py-2.5 bg-slate-50/50 border border-slate-200 rounded-lg text-sm text-slate-800 placeholder-slate-400 outline-none transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 disabled:opacity-60">
                    </div>
                    <div id="searchResults" class="hidden absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-slate-200 rounded-xl shadow-lg overflow-hidden w-full">
                        <div class="max-h-72 overflow-y-auto divide-y divide-slate-100" id="searchResultRows" role="listbox" aria-label="Hasil pencarian obat"></div>
                    </div>
                </div>

                <div id="stagingCard" class="hidden mt-4 p-4 sm:p-5 rounded-xl bg-indigo-50/70 border border-indigo-200 space-y-4">
                    <div class="flex items-center justify-between"><div><span class="text-[10px] uppercase font-bold text-indigo-600 tracking-wider">Batch terpilih</span><div class="font-semibold text-sm text-slate-900" id="stageBatch">—</div></div><button type="button" id="cancelStageBtn" aria-label="Tutup pilihan" class="w-8 h-8 rounded-lg border border-indigo-200 bg-white text-slate-500">×</button></div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2"><div class="rounded-lg bg-white/80 border border-indigo-100 p-3"><div class="text-[10px] uppercase text-slate-400 font-bold">Nama obat</div><div id="stageMedName" class="text-xs font-bold text-slate-800 truncate mt-1">—</div></div><div class="rounded-lg bg-white/80 border border-indigo-100 p-3"><div class="text-[10px] uppercase text-slate-400 font-bold">Satuan</div><div id="stageUnit" class="text-xs font-bold text-slate-800 mt-1">—</div></div><div class="rounded-lg bg-white/80 border border-indigo-100 p-3"><div class="text-[10px] uppercase text-slate-400 font-bold">Stok tersedia</div><div id="stageStock" class="text-xs font-bold text-emerald-700 mt-1">—</div></div></div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3"><div><label for="stageQty" class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-1">Qty permintaan</label><input type="number" id="stageQty" min="1" value="1" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-lg text-sm font-mono outline-none focus:border-indigo-500"></div><div><label for="stageEtalase" class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-1">Tujuan etalase</label><div class="flex gap-2"><select id="stageEtalase" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-lg text-sm"></select><button type="button" id="addEtalase" class="px-3 rounded-lg border border-slate-200 bg-white text-lg">+</button></div></div></div>
                    <div class="flex gap-2"><button type="button" id="addItemBtn" class="flex-1 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs rounded-lg">+ Tambah ke Daftar</button><button type="button" id="cancelStageBtn2" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-600 text-xs rounded-lg">Batal</button></div>
                </div>
            </section>

            <section class="bg-white rounded-xl border border-slate-200/80 shadow-sm p-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-md bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold text-xs">3</div>
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">Daftar Obat Diminta</h2>
                            <p class="text-[11px] text-slate-400 mt-0.5">Periksa jumlah sebelum mengirim permintaan</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100"><span id="cartCount">0</span>&nbsp;item</span>
                </div>

                <div class="overflow-x-auto border border-slate-200/80 rounded-lg">
                    <table class="w-full text-left text-xs min-w-[500px]">
                        <thead class="bg-slate-50 border-b border-slate-200/80 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                            <tr><th class="py-3 px-4">Batch</th><th class="py-3 px-4">Obat</th><th class="py-3 px-4">Satuan</th><th class="py-3 px-4">Stok</th><th class="py-3 px-4">Qty</th><th class="py-3 px-4">Etalase</th><th class="py-3 px-4"></th></tr>
                        </thead>
                        <tbody id="requestItemsBody" class="divide-y divide-slate-100 text-slate-700">
                            <tr id="cartEmptyRow"><td colspan="7" class="py-9 text-center text-slate-400 text-xs">Belum ada obat dalam permintaan</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center sm:justify-between gap-3 mt-5 pt-4 border-t border-slate-100">
                    <p class="text-[11px] text-slate-400">Cabang pengirim akan memeriksa ketersediaan sebelum menyetujui.</p>
                    <div class="flex gap-2 sm:justify-end">
                        <a href="{{ route('transfers.incoming') }}" class="inline-flex items-center justify-center px-4 py-2.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium text-xs rounded-lg transition">Batal</a>
                        <button type="submit" id="submitRequest" disabled class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-200 disabled:text-slate-400 text-white font-semibold text-xs rounded-lg shadow-sm transition">
                            Kirim Permintaan
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </button>
                    </div>
                </div>
            </section>
        </form>
    </div>

    <script>
        (() => {
            const searchInput=document.getElementById('medicineSearch'), dropdown=document.getElementById('searchResults'), results=document.getElementById('searchResultRows'), stage=document.getElementById('stagingCard'), body=document.getElementById('requestItemsBody'), form=document.getElementById('requestForm'), source=document.getElementById('sourcePharmacy'), cartCount=document.getElementById('cartCount'), submit=document.getElementById('submitRequest');
            const cart=new Map(); let staged=null, timer=null, active=-1, etalases=[];
            const escapeHtml = value => String(value ?? '—');
            function hideResults(){dropdown.classList.add('hidden');searchInput.setAttribute('aria-expanded','false');searchInput.removeAttribute('aria-activedescendant');active=-1;results.querySelectorAll('[role=option]').forEach(x=>x.setAttribute('aria-selected','false'));}
            function activate(i){const opts=[...results.querySelectorAll('[role=option]')];if(!opts.length)return;dropdown.classList.remove('hidden');searchInput.setAttribute('aria-expanded','true');active=Math.max(0,Math.min(i,opts.length-1));opts.forEach((o,n)=>{o.setAttribute('aria-selected',n===active?'true':'false');if(n===active){searchInput.setAttribute('aria-activedescendant',o.id);o.scrollIntoView({block:'nearest'});}});}
            async function loadEtalases(){const id=document.getElementById('stageEtalase');id.replaceChildren();try{const r=await fetch(`{{ route('etalases.index') }}?pharmacy_id=${encodeURIComponent({{ (int) getActivePharmacyId() }})}`,{headers:{Accept:'application/json'}});etalases=await r.json();etalases.forEach(e=>{const o=document.createElement('option');o.value=e.id;o.textContent=e.name;id.append(o);});}catch(e){etalases=[];}}
            function showStage(m){staged=m;document.getElementById('stageBatch').textContent=`${m.batches_name || '—'} · ${m.expired_date || 'Tanpa ED'} · ${m.source_type==='gudang'?'Gudang':'Pelayanan'}`;document.getElementById('stageMedName').textContent=m.name;document.getElementById('stageUnit').textContent=m.unit||'—';document.getElementById('stageStock').textContent=m.stock;const q=document.getElementById('stageQty');q.max=m.stock;q.value=1;stage.classList.remove('hidden');hideResults();searchInput.value='';loadEtalases().then(()=>q.focus());}
            function hidden(name,value){const x=document.createElement('input');x.type='hidden';x.name=name;x.value=value;return x;}
            function render(){body.replaceChildren();cartCount.textContent=cart.size;submit.disabled=!cart.size;if(!cart.size){body.innerHTML='<tr><td colspan="7" class="py-9 text-center text-slate-400 text-xs">Belum ada obat dalam permintaan</td></tr>';return;}let i=0;cart.forEach((m,key)=>{const tr=document.createElement('tr');tr.className='hover:bg-slate-50/70';const cell=(text,cls='')=>{const td=document.createElement('td');td.className=`py-3 px-3 ${cls}`;td.textContent=text;return td;};const batch=cell(`${m.batches_name||'—'} · ${m.expired_date||'Tanpa ED'}`,'font-medium text-slate-600');const medicine=cell(m.name,'font-semibold text-slate-800');const unit=cell(m.unit||'—');const stock=cell(m.stock,'font-mono text-emerald-700 font-bold');const qty=cell('');const input=document.createElement('input');input.type='number';input.min=1;input.max=m.stock;input.required=true;input.value=m.qty;input.name=`items[${i}][qty]`;input.className='w-20 px-2 py-1.5 border border-slate-200 rounded-md';input.addEventListener('input',()=>{m.qty=Math.max(1,Math.min(Number(input.max),parseInt(input.value)||1));});qty.append(input);const et=cell('');const select=document.createElement('select');select.name=`items[${i}][etalases_id]`;select.required=true;select.className='max-w-36 px-2 py-1.5 border border-slate-200 rounded-md';etalases.forEach(e=>{const o=document.createElement('option');o.value=e.id;o.textContent=e.name;o.selected=String(e.id)===String(m.etalases_id);select.append(o);});et.append(select);const del=cell('','text-center');const b=document.createElement('button');b.type='button';b.textContent='×';b.className='text-rose-500 text-lg';b.dataset.remove=key;del.append(b);tr.append(batch,medicine,unit,stock,qty,et,del,hidden(`items[${i}][batches_id]`,m.id),hidden(`items[${i}][medicine_id]`,m.medicine_id),hidden(`items[${i}][source_type]`,m.source_type));body.append(tr);i++;});}
            source.addEventListener('change',()=>{cart.clear();render();searchInput.value='';searchInput.disabled=!source.value;searchInput.placeholder=source.value?'Cari nama batch atau nama obat...':'Pilih cabang pengirim terlebih dahulu';results.replaceChildren();hideResults();stage.classList.add('hidden');staged=null;});
            searchInput.addEventListener('input',()=>{clearTimeout(timer);const q=searchInput.value.trim();if(q.length<1){hideResults();results.replaceChildren();return;}hideResults();timer=setTimeout(async()=>{try{const url=`{{ route('transfers.requests.medicines') }}?search=${encodeURIComponent(q)}&source_pharmacy_id=${encodeURIComponent(source.value)}`;const r=await fetch(url,{headers:{Accept:'application/json'}});const data=await r.json();results.replaceChildren();(data.data||[]).forEach((m,n)=>{const b=document.createElement('button');b.type='button';b.id=`request-result-${n}`;b.setAttribute('role','option');b.setAttribute('aria-selected','false');b.className='request-search-option w-full px-4 py-3 text-left hover:bg-indigo-50 border-b border-slate-100';b.medicine=m;const title=document.createElement('div');title.className='flex justify-between gap-3';const name=document.createElement('strong');name.className='text-xs text-slate-800';name.textContent=m.name;const stock=document.createElement('span');stock.className='text-xs font-bold text-emerald-700';stock.textContent=m.stock;title.append(name,stock);const meta=document.createElement('div');meta.className='text-[10px] text-slate-500 mt-1';meta.textContent=`${m.batches_name||'—'} · ${m.expired_date||'Tanpa ED'} · ${m.medicine_code||'—'} · ${m.unit||'—'}`;const type=document.createElement('span');type.className='inline-block mt-2 px-2 py-1 rounded border border-amber-200 bg-amber-50 text-[10px] text-amber-800';type.textContent=m.source_type==='gudang'?'Gudang → Pelayanan':'Pelayanan → Pelayanan';b.append(title,meta,type);b.onclick=()=>showStage(m);results.append(b);});if(!data.data?.length)results.innerHTML='<div class="p-4 text-center text-xs text-slate-400">Batch tidak ditemukan atau stok kosong</div>';dropdown.classList.remove('hidden');searchInput.setAttribute('aria-expanded','true');}catch(e){results.innerHTML='<div class="p-4 text-center text-xs text-rose-500">Pencarian gagal dimuat.</div>';dropdown.classList.remove('hidden');}},200);});
            searchInput.addEventListener('keydown',e=>{const opts=results.querySelectorAll('[role=option]');if(e.key==='ArrowDown'&&opts.length){e.preventDefault();activate(active<0?0:active+1);}else if(e.key==='ArrowUp'&&opts.length){e.preventDefault();activate(active<0?opts.length-1:active-1);}else if(e.key==='Enter'&&!dropdown.classList.contains('hidden')&&opts.length){e.preventDefault();(opts[active<0?0:active]).medicine&&showStage(opts[active<0?0:active].medicine);}else if(e.key==='Escape')hideResults();});
            document.getElementById('addItemBtn').onclick=()=>{if(!staged)return;const qtyInput=document.getElementById('stageQty'),qty=Math.max(1,Math.min(staged.stock,parseInt(qtyInput.value)||1)),etalase=document.getElementById('stageEtalase').value;if(!etalase){alert('Pilih atau buat etalase tujuan terlebih dahulu.');return;}cart.set(String(staged.id),{...staged,qty,etalases_id:etalase});staged=null;stage.classList.add('hidden');render();searchInput.focus();};
            document.getElementById('stageQty').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();const etalase=document.getElementById('stageEtalase');etalase.focus();try{etalase.showPicker?.();}catch(_){}}});
            document.getElementById('stageEtalase').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();document.getElementById('addItemBtn').click();}});
            const cancel=()=>{staged=null;stage.classList.add('hidden');};document.getElementById('cancelStageBtn').onclick=cancel;document.getElementById('cancelStageBtn2').onclick=cancel;
            body.addEventListener('click',e=>{const key=e.target.closest('[data-remove]')?.dataset.remove;if(key){cart.delete(key);render();}});
            body.addEventListener('change',e=>{const tr=e.target.closest('tr');if(tr){const key=[...cart.keys()][[...body.children].indexOf(tr)];if(key!==undefined&&e.target.tagName==='SELECT')cart.get(key).etalases_id=e.target.value;}});
            document.getElementById('addEtalase').onclick=async()=>{const name=prompt('Nama etalase baru');if(!name?.trim())return;try{const r=await fetch(`{{ route('etalases.store') }}`,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},body:JSON.stringify({name:name.trim(),pharmacy_id:{{ (int) getActivePharmacyId() }}})});if(!r.ok)throw new Error();const created=await r.json();etalases.push(created);const o=document.createElement('option');o.value=created.id;o.textContent=created.name;o.selected=true;document.getElementById('stageEtalase').append(o);}catch(e){alert('Etalase gagal dibuat.');}};
            document.addEventListener('click',e=>{if(!document.getElementById('searchWrapper').contains(e.target))hideResults();});form.addEventListener('submit',e=>{if(!cart.size||!source.value){e.preventDefault();return;}for(const m of cart.values())if(m.qty<1||m.qty>m.stock||!m.etalases_id){e.preventDefault();alert('Periksa qty dan etalase tujuan.');return;}});render();
        })();
    </script>
@endsection
