document.querySelector(".dataTable-bottom");document.querySelector(".dataTable-pagination-list");document.getElementById("prevPage");document.getElementById("nextPage");const O=document.getElementById("myTable"),U=Array.from(O.querySelector("tbody").rows);Math.ceil(U.length/10);const J=document.querySelectorAll(".viewTask");J.forEach(i=>{i.addEventListener("click",function(c){c.preventDefault();const e=this.getAttribute("data-task-id"),s=this.getAttribute("data-task-url");K(e,s)})});function K(i,c){if(currentlyDisplayed===`task-${i}`){W();return}Q(),fetch(c).then(e=>{if(!e.ok)throw new Error(`Failed to fetch task details: ${e.status}`);return e.json()}).then(e=>{var s,x,m,$,u,f,_,E,k,L,C,F,I,B,y,p,N,b,S,D,T,g,h;e&&e.client_name?(e.type,e.type==="flight"?`${e.country_from}${e.country_to}`:e.hotel_name,console.log("data : ",e),taskDetailsDiv.innerHTML=`
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
            <i class="fas fa-warehouse"></i> ${((s=e.supplier)==null?void 0:s.name)||"N/A"}
        </h3>
    </div>

    <div class="section status">
        <h4><i class="fas fa-info-circle blue-icon"></i> Status: <span class="status-label">${e.status}</span></h4>
        <div class="info-row">
            <p><i class="fas fa-map-marker-alt blue-icon"></i> <strong>Branch:</strong> ${((m=(x=e.agent)==null?void 0:x.branch)==null?void 0:m.name)||"N/A"}</p>
            <p><i class="fas fa-user-tie blue-icon"></i> <strong>Agent:</strong> ${(($=e.agent)==null?void 0:$.name)||"N/A"}</p>
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
            <p><i class="fas fa-plane-departure blue-icon"></i> <strong>Departure:</strong> ${((u=e.flight_details)==null?void 0:u.airport_from)||"N/A"} - ${((f=e.flight_details)==null?void 0:f.departure_time)||"N/A"}</p>
            <p><i class="fas fa-plane-arrival blue-icon"></i> <strong>Arrival:</strong> ${((_=e.flight_details)==null?void 0:_.airport_to)||"N/A"} - ${((E=e.flight_details)==null?void 0:E.arrival_time)||"N/A"}</p>
            <p><i class="fas fa-ticket-alt blue-icon"></i> <strong>Flight:</strong> ${((k=e.flight_details)==null?void 0:k.flight_number)||"N/A"} - ${((L=e.flight_details)==null?void 0:L.class_type)||"N/A"}</p>
            <p><i class="fas fa-suitcase blue-icon"></i> <strong>Baggage:</strong> ${((C=e.flight_details)==null?void 0:C.baggage_allowed)||"N/A"}</p>
        </div>
    </div>`:`
    <div class="section hotel-details">
        <h4><i class="fas fa-hotel blue-icon"></i> Hotel Details</h4>
        <div class="info-row">
            <p><i class="fas fa-building blue-icon"></i> <strong>Hotel:</strong> ${e.hotel_name||"N/A"}</p>
            <p><i class="fas fa-map-marker-alt blue-icon"></i> <strong>Location:</strong> ${((I=(F=e.hotel_details)==null?void 0:F.hotel)==null?void 0:I.address)||"N/A"}, ${((y=(B=e.hotel_details)==null?void 0:B.hotel)==null?void 0:y.city)||"N/A"}</p>
            <p><i class="fas fa-globe blue-icon"></i> <strong>Country:</strong> ${e.hotel_country||"N/A"}</p>
            <p><i class="fas fa-calendar-check blue-icon"></i> <strong>Check-in:</strong> ${((p=e.hotel_details)==null?void 0:p.check_in)||"N/A"}</p>
            <p><i class="fas fa-calendar-times blue-icon"></i> <strong>Check-out:</strong> ${((N=e.hotel_details)==null?void 0:N.check_out)||"N/A"}</p>
            <p><i class="fas fa-star blue-icon"></i> <strong>Rating:</strong> ${((S=(b=e.hotel_details)==null?void 0:b.hotel)==null?void 0:S.rating)||"N/A"}</p>
            <p><i class="fas fa-star blue-icon"></i> <strong>Room Reference:</strong> ${((T=(D=e.hotel_details)==null?void 0:D.hotel)==null?void 0:T.room_reference)||"N/A"}</p>
            <p><i class="fas fa-bed blue-icon"></i> <strong>Room:</strong> ${((g=e.hotel_details)==null?void 0:g.room_type)||"N/A"} - ${((h=e.hotel_details)==null?void 0:h.room_number)||"N/A"}</p>
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

`,taskDetailsDiv.style.display="block",showRightDiv.classList.remove("hidden"),currentlyDisplayed=`task-${i}`):console.warn("Invalid Data:",e)}).catch(e=>{console.error("Error fetching task details:",e)})}const H=document.querySelector(".content-70");function Q(i){H.classList.add("shrink"),showRightDiv.classList.add("visible"),taskDetailsDiv.style.display="block"}function W(){currentlyDisplayed=null,H.classList.remove("shrink"),showRightDiv.classList.remove("visible"),taskDetailsDiv.style.display="none"}const q=document.getElementById("floatingActions"),X=document.getElementById("closeTaskFloatingActions"),R=document.getElementById("selectAll"),w=document.querySelectorAll(".rowCheckbox"),P=document.getElementById("createInvoiceBtn");R&&R.addEventListener("change",function(){w.forEach(i=>i.checked=R.checked),j()});const j=()=>{const i=Array.from(w).some(c=>c.checked);P.disabled=!i};w.forEach(i=>{i.addEventListener("change",function(){const c=Array.from(w).every(s=>s.checked);R.checked=c,j(),Array.from(w).some(s=>s.checked)?q.classList.remove("hidden"):q.classList.add("hidden")})});j();P.addEventListener("click",function(){const i=Array.from(w).filter(s=>s.checked).map(s=>s.value);if(i.length===0){alert("No tasks selected!");return}console.log(i);const e=this.getAttribute("data-route")+"?task_ids="+i.join(",");window.location.href=e});X.addEventListener("click",function(){q.classList.add("hidden")});document.addEventListener("DOMContentLoaded",function(){const i={columns:{reference:{label:"Reference",type:"text"},gds_reference:{label:"GDS Reference",type:"text"},amadeus_reference:{label:"Amadeus Reference",type:"text"},branch_name:{label:"Branch Name",type:"text"},agent_name:{label:"Agent Name",type:"text"},date:{label:"Date",type:"date"},type:{label:"Type",type:"select",options:["hotel","flight"]},price:{label:"Price",type:"number"},status:{label:"Status",type:"select",options:["issued","refund","reissued","void","ticketed","confirmed"]},supplier:{label:"Supplier",type:"text"}}};let c=[],e=0;const s=new Map,x=document.getElementById("toggleFilters"),m=document.getElementById("filterModal"),$=document.getElementById("closeFilterModal"),u=document.getElementById("filterContainer"),f=document.getElementById("addFilterRow"),_=document.getElementById("applyFilters"),E=document.getElementById("clearAllFilters"),k=document.getElementById("activeFiltersContainer"),L=document.getElementById("activeFiltersList"),C=document.getElementById("clearAllActiveFilters"),F=document.getElementById("searchInput"),I=document.getElementById("noTasksFound");x.addEventListener("click",B),$.addEventListener("click",y),f.addEventListener("click",p),_.addEventListener("click",S),E.addEventListener("click",D),C.addEventListener("click",T),m.addEventListener("click",function(t){t.target===m&&y()});function B(){m.classList.add("active"),u.children.length===0&&p(),b()}function y(){m.classList.remove("active")}function p(){const t=++e,n=document.createElement("div");n.className="filter-row",n.dataset.filterId=t,s.set(t,{column:"",value:""});const l=Object.entries(i.columns).filter(([o])=>!N(o));l.length!==0&&(n.innerHTML=`
            <select class="column-select w-48" onchange="updateConditions(this)">
                ${l.map(([o,r])=>`<option value="${o}" data-type="${r.type}">${r.label}</option>`).join("")}
            </select>
            <input type="text" class="value-input" placeholder="Enter value...">
            <button type="button" class="remove-filter-btn" onclick="removeFilterRow(${t})">
                &times;
            </button>
        `,u.appendChild(n),updateConditions(n.querySelector(".column-select")),b())}function N(t){return Array.from(u.querySelectorAll(".column-select")).map(l=>l.value).includes(t)}function b(){const t=u.children.length,n=Object.keys(i.columns).length;t>=n?(f.disabled=!0,f.classList.add("opacity-50","cursor-not-allowed")):(f.disabled=!1,f.classList.remove("opacity-50","cursor-not-allowed"))}window.updateConditions=function(t){const n=t.closest(".filter-row"),l=parseInt(n.dataset.filterId),o=t.value,r=i.columns[o].type;let d=n.querySelector(".value-input");s.get(l).column=o;const v=d.value;s.get(l).value=v;let a;r==="select"?(a=document.createElement("select"),a.className="value-input",a.innerHTML=`<option value="">Select value...</option>
                ${i.columns[o].options.map(M=>`<option value="${M}">${M}</option>`).join("")}`):(a=document.createElement("input"),a.className="value-input",a.placeholder="Enter value...",a.type=r==="date"?"date":r==="number"?"number":"text"),n.replaceChild(a,d),d=a;const A=s.get(l).value;A&&(d.value=A),d.addEventListener("input",()=>{s.get(l).value=d.value})},window.removeFilterRow=function(t){const n=document.querySelector(`[data-filter-id="${t}"]`);n&&(n.remove(),s.delete(t),b(),u.children.length===0&&p())};function S(){c=[],s.forEach((t,n)=>{const l=document.querySelector(`[data-filter-id="${n}"]`);if(!l)return;const o=l.querySelector(".column-select"),r=l.querySelector(".value-input"),d=o?o.value:"",v=r?r.value:"";d&&v&&c.push({column:d,value:v,label:`${i.columns[d].label} "${v}"`})}),g(),h(),y()}function D(){u.innerHTML="",s.clear(),c=[],g(),h(),p()}function T(){u.innerHTML="",s.clear(),c=[],g(),h(),p()}function g(){if(c.length===0){k.style.display="none";return}k.style.display="block",L.innerHTML=c.map((t,n)=>`
            <div class="active-filter-tag">
                ${t.label}
                <button class="remove-tag" onclick="removeActiveFilter(${n})">&times;</button>
            </div>
        `).join("")}window.removeActiveFilter=function(t){c.splice(t,1),g(),h()};function h(){const t=document.querySelectorAll("#myTable tbody tr.taskRow");let n=!1;t.forEach(l=>{let o=!0;c.length>0&&(o=c.every(a=>{const A=V(l,a.column);return a.column==="date"?A.split(" ")[0]===a.value:G(A,a.value)}));const r=F.value.toLowerCase().trim(),v=l.textContent.toLowerCase().includes(r);o=o&&v,o?(l.classList.remove("js-hidden"),n=!0):l.classList.add("js-hidden")}),I.style.display=n?"none":"flex"}function V(t,n){const l=z(n);if(l===-1)return"";const o=t.cells[l];return o?o.textContent.trim():""}function z(t){const n=document.querySelector("#myTable thead tr"),l=Array.from(n.cells),r={reference:"Reference",gds_reference:"GDS Reference",amadeus_reference:"Amadeus Reference",branch_name:"Branch Name",agent_name:"Agent Name",date:"Date",type:"Type",price:"Price",status:"Status",supplier:"Supplier"}[t];return l.findIndex(d=>d.textContent.trim()===r)}function G(t,n){const l=t.toLowerCase(),o=n.toLowerCase();return l.includes(o)}});
