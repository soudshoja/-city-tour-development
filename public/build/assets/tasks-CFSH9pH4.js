document.querySelector(".dataTable-bottom");document.querySelector(".dataTable-pagination-list");document.getElementById("prevPage");document.getElementById("nextPage");const O=document.getElementById("myTable"),G=Array.from(O.querySelector("tbody").rows);Math.ceil(G.length/10);const U=document.querySelectorAll(".viewTask");U.forEach(o=>{o.addEventListener("click",function(i){i.preventDefault();const e=this.getAttribute("data-task-id"),c=this.getAttribute("data-task-url");W(e,c)})});function W(o,i){if(currentlyDisplayed===`task-${o}`){K();return}J(),fetch(i).then(e=>{if(!e.ok)throw new Error(`Failed to fetch task details: ${e.status}`);return e.json()}).then(e=>{var c,u,f,r,p,k,m,A,_,I,x,E,C,v,y,B,F,T,N,h,S,g,$;e&&e.client_name?(e.type,e.type==="flight"?`${e.country_from}${e.country_to}`:e.hotel_name,console.log("data : ",e),taskDetailsDiv.innerHTML=`
                               <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
                                                <style>
                       .task-container {
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    background: #ffffff;
    border-radius: 12px;
    font-family: "Arial", sans-serif;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1);
}

/* Supplier Name - Unique Design */
.supplier-name {
    text-align: center;
    font-size: 22px;
    font-weight: bold;
    color: #0047ab;
    background: rgba(0, 71, 171, 0.1);
    padding: 10px;
    border-radius: 8px;
}

/* Section Styling */
.section {
    padding: 15px;
    margin: 15px 0;
    border-radius: 8px;
    background: #f8f9fa;
    border-left: 5px solid #0047ab;
}

/* Unique Backgrounds for Each Section */

.pricing.hotel-details.flight-details.general-info.status {
    background: #fff3cd;
}


/* Icons in Blue */
.blue-icon {
    color: #0047ab;
}

/* Flexbox for Better Alignment */
.info-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.info-row p {
    flex: 1;
    min-width: 45%;
    margin: 5px 0;
}

/* Status Label Styling */
.status-label {
    font-weight: bold;
    padding: 5px 12px;
    border-radius: 5px;
    background: #0047ab;
    color: white;
}

</style>
   <div class="task-container">
    <div class="header">
        <h3 class="supplier-name">
            <i class="fas fa-warehouse"></i> ${((c=e.supplier)==null?void 0:c.name)||"N/A"}
        </h3>
    </div>

    <div class="section status">
        <h4><i class="fas fa-info-circle blue-icon"></i> Status: <span class="status-label">${e.status}</span></h4>
        <div class="info-row">
            <p><i class="fas fa-map-marker-alt blue-icon"></i> <strong>Branch:</strong> ${((f=(u=e.agent)==null?void 0:u.branch)==null?void 0:f.name)||"N/A"}</p>
            <p><i class="fas fa-user-tie blue-icon"></i> <strong>Agent:</strong> ${((r=e.agent)==null?void 0:r.name)||"N/A"}</p>
            <p><i class="fas fa-user blue-icon"></i> <strong>Client:</strong> 
            ${e.client_name!==void 0&&e.client_name!==null?e.client_name:"N/A"}</p>

        </div>
    </div>

    <div class="section general-info">
        <h4><i class="fas fa-info-circle blue-icon"></i> General Information</h4>
        <div class="info-row">
            <p><i class="fas fa-hashtag blue-icon"></i> <strong>Reference:</strong> ${e.reference||"N/A"}</p>
            <p><i class="fas fa-tag blue-icon"></i> <strong>Type:</strong> ${e.type}</p>
            <p><i class="fas fa-ticket-alt blue-icon"></i> <strong>Ticket Number:</strong> ${e.ticket_number||"N/A"}</p>
        </div>
    </div>

    ${e.type==="flight"?`
    <div class="section flight-details">
        <h4><i class="fas fa-plane blue-icon"></i> Flight Details</h4>
        <div class="info-row">
            <p><i class="fas fa-plane-departure blue-icon"></i> ${e.country_from} <i class="fas fa-plane blue-icon"></i> ${e.country_to}</p>
            <p><i class="fas fa-plane-departure blue-icon"></i> <strong>Departure:</strong> ${((p=e.flight_details)==null?void 0:p.airport_from)||"N/A"} - ${((k=e.flight_details)==null?void 0:k.departure_time)||"N/A"}</p>
            <p><i class="fas fa-plane-arrival blue-icon"></i> <strong>Arrival:</strong> ${((m=e.flight_details)==null?void 0:m.airport_to)||"N/A"} - ${((A=e.flight_details)==null?void 0:A.arrival_time)||"N/A"}</p>
            <p><i class="fas fa-ticket-alt blue-icon"></i> <strong>Flight:</strong> ${((_=e.flight_details)==null?void 0:_.flight_number)||"N/A"} - ${((I=e.flight_details)==null?void 0:I.class_type)||"N/A"}</p>
            <p><i class="fas fa-suitcase blue-icon"></i> <strong>Baggage:</strong> ${((x=e.flight_details)==null?void 0:x.baggage_allowed)||"N/A"}</p>
        </div>
    </div>`:`
    <div class="section hotel-details">
        <h4><i class="fas fa-hotel blue-icon"></i> Hotel Details</h4>
        <div class="info-row">
            <p><i class="fas fa-building blue-icon"></i> <strong>Hotel:</strong> ${e.hotel_name||"N/A"}</p>
            <p><i class="fas fa-map-marker-alt blue-icon"></i> <strong>Location:</strong> ${((C=(E=e.hotel_details)==null?void 0:E.hotel)==null?void 0:C.address)||"N/A"}, ${((y=(v=e.hotel_details)==null?void 0:v.hotel)==null?void 0:y.city)||"N/A"}</p>
            <p><i class="fas fa-globe blue-icon"></i> <strong>Country:</strong> ${e.hotel_country||"N/A"}</p>
            <p><i class="fas fa-calendar-check blue-icon"></i> <strong>Check-in:</strong> ${((B=e.hotel_details)==null?void 0:B.check_in)||"N/A"}</p>
            <p><i class="fas fa-calendar-times blue-icon"></i> <strong>Check-out:</strong> ${((F=e.hotel_details)==null?void 0:F.check_out)||"N/A"}</p>
            <p><i class="fas fa-star blue-icon"></i> <strong>Rating:</strong> ${((N=(T=e.hotel_details)==null?void 0:T.hotel)==null?void 0:N.rating)||"N/A"}</p>
            <p><i class="fas fa-star blue-icon"></i> <strong>Room Reference:</strong> ${((S=(h=e.hotel_details)==null?void 0:h.hotel)==null?void 0:S.room_reference)||"N/A"}</p>
            <p><i class="fas fa-bed blue-icon"></i> <strong>Room:</strong> ${((g=e.hotel_details)==null?void 0:g.room_type)||"N/A"} - ${(($=e.hotel_details)==null?void 0:$.room_number)||"N/A"}</p>
        </div>
    </div>`}

    <div class="section pricing">
        <h4><i class="fas fa-coins blue-icon"></i> Pricing Details</h4>
        <div class="info-row">
            <p><i class="fas fa-money-bill blue-icon"></i> <strong>Price:</strong> ${e.price||"N/A"}</p>
            <p><i class="fas fa-percentage blue-icon"></i> <strong>Tax:</strong> ${e.tax||"N/A"}</p>
            <p><i class="fas fa-calculator blue-icon"></i> <strong>Total:</strong> ${e.total||"N/A"}</p>
        </div>
    </div>
</div>

`,taskDetailsDiv.style.display="block",showRightDiv.classList.remove("hidden"),currentlyDisplayed=`task-${o}`):console.warn("Invalid Data:",e)}).catch(e=>{console.error("Error fetching task details:",e)})}const H=document.querySelector(".content-70");function J(o){H.classList.add("shrink"),showRightDiv.classList.add("visible"),taskDetailsDiv.style.display="block"}function K(){currentlyDisplayed=null,H.classList.remove("shrink"),showRightDiv.classList.remove("visible"),taskDetailsDiv.style.display="none"}const R=document.getElementById("floatingActions"),Q=document.getElementById("closeTaskFloatingActions"),M=document.getElementById("selectAll"),w=document.querySelectorAll(".rowCheckbox"),P=document.getElementById("createInvoiceBtn");M.addEventListener("change",function(){w.forEach(o=>o.checked=M.checked),q()});const q=()=>{const o=Array.from(w).some(i=>i.checked);P.disabled=!o};w.forEach(o=>{o.addEventListener("change",function(){const i=Array.from(w).every(c=>c.checked);M.checked=i,q(),Array.from(w).some(c=>c.checked)?R.classList.remove("hidden"):R.classList.add("hidden")})});q();P.addEventListener("click",function(){const o=Array.from(w).filter(c=>c.checked).map(c=>c.value);if(o.length===0){alert("No tasks selected!");return}console.log(o);const e=this.getAttribute("data-route")+"?task_ids="+o.join(",");window.location.href=e});Q.addEventListener("click",function(){R.classList.add("hidden")});document.addEventListener("DOMContentLoaded",function(){const o=document.getElementById("searchInput"),e=document.getElementById("myTable").querySelectorAll("tbody tr.taskRow"),c=document.getElementById("noTasksFound"),u=document.getElementById("loadMoreWrapper");o.addEventListener("input",function(){const f=this.value.toLowerCase().trim();let r=!1;e.forEach(p=>{const m=p.textContent.toLowerCase().includes(f);p.style.display=m?"":"none",m&&(r=!0)}),c.classList.toggle("hidden",r),f.length>0?u.classList.add("hidden"):u.classList.remove("hidden")})});document.addEventListener("DOMContentLoaded",function(){const o={columns:{reference:{label:"Reference",type:"text"},gds_reference:{label:"GDS Reference",type:"text"},amadeus_reference:{label:"Amadeus Reference",type:"text"},branch_name:{label:"Branch Name",type:"text"},agent_name:{label:"Agent Name",type:"text"},date:{label:"Date",type:"date"},type:{label:"Type",type:"select",options:["hotel","flight"]},price:{label:"Price",type:"number"},status:{label:"Status",type:"select",options:["issued","refund","reissued","void","ticketed","confirmed"]},supplier:{label:"Supplier",type:"text"}}};let i=[],e=0;const c=document.getElementById("toggleFilters"),u=document.getElementById("filterModal"),f=document.getElementById("closeFilterModal"),r=document.getElementById("filterContainer"),p=document.getElementById("addFilterRow"),k=document.getElementById("applyFilters"),m=document.getElementById("clearAllFilters"),A=document.getElementById("activeFiltersContainer"),_=document.getElementById("activeFiltersList"),I=document.getElementById("clearAllActiveFilters"),x=document.getElementById("searchInput"),E=document.getElementById("noTasksFound");c.addEventListener("click",C),f.addEventListener("click",v),p.addEventListener("click",y),k.addEventListener("click",F),m.addEventListener("click",T),I.addEventListener("click",N),u.addEventListener("click",function(l){l.target===u&&v()});function C(){u.classList.add("active"),r.children.length===0&&y()}function v(){u.classList.remove("active")}function y(){const l=++e,n=document.createElement("div");n.className="filter-row",n.dataset.filterId=l,n.innerHTML=`
            <select class="column-select w-48" onchange="updateConditions(this)">
                ${Object.entries(o.columns).filter(([t])=>!B(t)).map(([t,s])=>`<option value="${t}" data-type="${s.type}">${s.label}</option>`).join("")}
            </select>
            <input type="text" class="value-input" placeholder="Enter value...">
            <button type="button" class="remove-filter-btn" onclick="removeFilterRow(${l})">
                &times;
            </button>
        `,r.appendChild(n)}function B(l){return Array.from(r.querySelectorAll(".column-select")).map(t=>t.value).includes(l)}window.updateConditions=function(l){const n=l.closest(".filter-row"),t=n.querySelector(".value-input"),a=l.selectedOptions[0].dataset.type;if(t.disabled=!1,a==="date"?t.type="date":a==="number"?t.type="number":t.type="text",a==="select"){const d=l.value,D=o.columns[d].options||[],b=document.createElement("select");b.className="value-input",b.innerHTML=`<option value="">Select value...</option>
                ${D.map(L=>`<option value="${L}">${L}</option>`).join("")}`,n.replaceChild(b,t)}else{const d=document.createElement("input");d.type=a==="date"?"date":a==="number"?"number":"text",d.className="value-input",d.placeholder="Enter value...",n.replaceChild(d,t)}},window.removeFilterRow=function(l){const n=document.querySelector(`[data-filter-id="${l}"]`);if(n&&r.children.length>1){const t=n.querySelector(".value-input");t&&(t.value=""),n.remove()}};function F(){const l=r.querySelectorAll(".filter-row");i=[],l.forEach(n=>{const t=n.querySelector(".column-select").value,s=n.querySelector(".value-input").value;if(t&&s)if(t==="date"){const a=S(s);i.push({column:t,value:a,label:`${o.columns[t].label} "${s}"`})}else i.push({column:t,value:s,label:`${o.columns[t].label} "${s}"`})}),h(),g(),v()}function T(){r.innerHTML="",i=[],r.querySelectorAll(".value-input").forEach(n=>{n.value=""}),h(),g(),y()}function N(){r.innerHTML="",i=[],r.querySelectorAll(".value-input").forEach(n=>{n.value=""}),h(),g()}function h(){if(i.length===0){A.style.display="none";return}A.style.display="block",_.innerHTML=i.map((l,n)=>`
            <div class="active-filter-tag">
                ${l.label}
                <button class="remove-tag" onclick="removeActiveFilter(${n})">&times;</button>
            </div>
        `).join("")}window.removeActiveFilter=function(l){i.splice(l,1),h(),g()};function S(l){const[n,t,s]=l.split("-");return`${s}-${t}-${n}`}function g(){const l=document.querySelectorAll("#myTable tbody tr.taskRow");let n=!1;l.forEach(t=>{let s=!0;i.length>0&&(s=i.every(a=>{const d=$(t,a.column);return a.column==="date"?d.split(" ")[0]===a.value:V(d,a.value)})),t.style.display=s?"":"none",s&&(n=!0)}),E.style.display=n?"none":"flex"}function $(l,n){const t=j(n);if(t===-1)return"";const s=l.cells[t];return s?s.textContent.trim():""}function j(l){const n=document.querySelector("#myTable thead tr"),t=Array.from(n.cells),a={reference:"Reference",gds_reference:"GDS Reference",amadeus_reference:"Amadeus Reference",branch_name:"Branch Name",agent_name:"Agent Name",date:"Date",type:"Type",price:"Price",status:"Status",supplier:"Supplier"}[l];return t.findIndex(d=>d.textContent.trim()===a)}function V(l,n){const t=l.toLowerCase(),s=n.toLowerCase();return t.includes(s)}x&&x.addEventListener("input",function(){const l=this.value.toLowerCase().trim(),n=document.querySelectorAll("#myTable tbody tr.taskRow");let t=!1;n.forEach(s=>{const d=s.textContent.toLowerCase().includes(l),D=i.length===0||i.every(L=>{const z=$(s,L.column);return V(z,L.value)}),b=d&&D;s.style.display=b?"":"none",b&&(t=!0)}),E.style.display=t?"none":"flex"})});
