document.querySelector(".dataTable-bottom");document.querySelector(".dataTable-pagination-list");document.getElementById("prevPage");document.getElementById("nextPage");const O=document.getElementById("myTable"),U=Array.from(O.querySelector("tbody").rows);Math.ceil(U.length/10);const J=document.querySelectorAll(".viewTask");J.forEach(s=>{s.addEventListener("click",function(c){c.preventDefault();const e=this.getAttribute("data-task-id"),o=this.getAttribute("data-task-url");K(e,o)})});function K(s,c){if(currentlyDisplayed===`task-${s}`){W();return}Q(),fetch(c).then(e=>{if(!e.ok)throw new Error(`Failed to fetch task details: ${e.status}`);return e.json()}).then(e=>{e&&e.client_name?(e.type,e.type==="flight"?`${e.country_from}${e.country_to}`:e.hotel_name,console.log("data : ",e),taskDetailsDiv.innerHTML=`
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
            <i class="fas fa-warehouse"></i> ${e.supplier?.name||"N/A"}
        </h3>
    </div>

    <div class="section status">
        <h4><i class="fas fa-info-circle blue-icon"></i> Status: <span class="status-label">${e.status}</span></h4>
        <div class="info-row">
            <p><i class="fas fa-map-marker-alt blue-icon"></i> <strong>Branch:</strong> ${e.agent?.branch?.name||"N/A"}</p>
            <p><i class="fas fa-user-tie blue-icon"></i> <strong>Agent:</strong> ${e.agent?.name||"N/A"}</p>
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
            <p><i class="fas fa-plane-departure blue-icon"></i> <strong>Departure:</strong> ${e.flight_details?.airport_from||"N/A"} - ${e.flight_details?.departure_time||"N/A"}</p>
            <p><i class="fas fa-plane-arrival blue-icon"></i> <strong>Arrival:</strong> ${e.flight_details?.airport_to||"N/A"} - ${e.flight_details?.arrival_time||"N/A"}</p>
            <p><i class="fas fa-ticket-alt blue-icon"></i> <strong>Flight:</strong> ${e.flight_details?.flight_number||"N/A"} - ${e.flight_details?.class_type||"N/A"}</p>
            <p><i class="fas fa-suitcase blue-icon"></i> <strong>Baggage:</strong> ${e.flight_details?.baggage_allowed||"N/A"}</p>
        </div>
    </div>`:`
    <div class="section hotel-details">
        <h4><i class="fas fa-hotel blue-icon"></i> Hotel Details</h4>
        <div class="info-row">
            <p><i class="fas fa-building blue-icon"></i> <strong>Hotel:</strong> ${e.hotel_name||"N/A"}</p>
            <p><i class="fas fa-map-marker-alt blue-icon"></i> <strong>Location:</strong> ${e.hotel_details?.hotel?.address||"N/A"}, ${e.hotel_details?.hotel?.city||"N/A"}</p>
            <p><i class="fas fa-globe blue-icon"></i> <strong>Country:</strong> ${e.hotel_country||"N/A"}</p>
            <p><i class="fas fa-calendar-check blue-icon"></i> <strong>Check-in:</strong> ${e.hotel_details?.check_in||"N/A"}</p>
            <p><i class="fas fa-calendar-times blue-icon"></i> <strong>Check-out:</strong> ${e.hotel_details?.check_out||"N/A"}</p>
            <p><i class="fas fa-star blue-icon"></i> <strong>Rating:</strong> ${e.hotel_details?.hotel?.rating||"N/A"}</p>
            <p><i class="fas fa-star blue-icon"></i> <strong>Room Reference:</strong> ${e.hotel_details?.hotel?.room_reference||"N/A"}</p>
            <p><i class="fas fa-bed blue-icon"></i> <strong>Room:</strong> ${e.hotel_details?.room_type||"N/A"} - ${e.hotel_details?.room_number||"N/A"}</p>
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

`,taskDetailsDiv.style.display="block",showRightDiv.classList.remove("hidden"),currentlyDisplayed=`task-${s}`):console.warn("Invalid Data:",e)}).catch(e=>{console.error("Error fetching task details:",e)})}const L=document.querySelector(".content-70");function Q(s){L.classList.add("shrink"),showRightDiv.classList.add("visible"),taskDetailsDiv.style.display="block"}function W(){currentlyDisplayed=null,L.classList.remove("shrink"),showRightDiv.classList.remove("visible"),taskDetailsDiv.style.display="none"}const $=document.getElementById("floatingActions"),X=document.getElementById("closeTaskFloatingActions"),w=document.getElementById("selectAll"),p=document.querySelectorAll(".rowCheckbox"),C=document.getElementById("createInvoiceBtn");w&&w.addEventListener("change",function(){p.forEach(s=>s.checked=w.checked),_()});const _=()=>{const s=Array.from(p).some(c=>c.checked);C.disabled=!s};p.forEach(s=>{s.addEventListener("change",function(){const c=Array.from(p).every(o=>o.checked);w.checked=c,_(),Array.from(p).some(o=>o.checked)?$.classList.remove("hidden"):$.classList.add("hidden")})});_();C.addEventListener("click",function(){const s=Array.from(p).filter(o=>o.checked).map(o=>o.value);if(s.length===0){alert("No tasks selected!");return}console.log(s);const e=this.getAttribute("data-route")+"?task_ids="+s.join(",");window.location.href=e});X.addEventListener("click",function(){$.classList.add("hidden")});document.addEventListener("DOMContentLoaded",function(){const s={columns:{reference:{label:"Reference",type:"text"},gds_reference:{label:"GDS Reference",type:"text"},amadeus_reference:{label:"Amadeus Reference",type:"text"},branch_name:{label:"Branch Name",type:"text"},agent_name:{label:"Agent Name",type:"text"},date:{label:"Date",type:"date"},type:{label:"Type",type:"select",options:["hotel","flight"]},price:{label:"Price",type:"number"},status:{label:"Status",type:"select",options:["issued","refund","reissued","void","ticketed","confirmed"]},supplier:{label:"Supplier",type:"text"}}};let c=[],e=0;const o=new Map,F=document.getElementById("toggleFilters"),v=document.getElementById("filterModal"),I=document.getElementById("closeFilterModal"),u=document.getElementById("filterContainer"),m=document.getElementById("addFilterRow"),B=document.getElementById("applyFilters"),N=document.getElementById("clearAllFilters"),E=document.getElementById("activeFiltersContainer"),S=document.getElementById("activeFiltersList"),D=document.getElementById("clearAllActiveFilters"),T=document.getElementById("searchInput"),R=document.getElementById("noTasksFound");F.addEventListener("click",M),I.addEventListener("click",k),m.addEventListener("click",g),B.addEventListener("click",j),N.addEventListener("click",H),D.addEventListener("click",P),v.addEventListener("click",function(t){t.target===v&&k()});function M(){v.classList.add("active"),u.children.length===0&&g(),A()}function k(){v.classList.remove("active")}function g(){const t=++e,n=document.createElement("div");n.className="filter-row",n.dataset.filterId=t,o.set(t,{column:"",value:""});const l=Object.entries(s.columns).filter(([i])=>!q(i));l.length!==0&&(n.innerHTML=`
            <select class="column-select w-48" onchange="updateConditions(this)">
                ${l.map(([i,r])=>`<option value="${i}" data-type="${r.type}">${r.label}</option>`).join("")}
            </select>
            <input type="text" class="value-input" placeholder="Enter value...">
            <button type="button" class="remove-filter-btn" onclick="removeFilterRow(${t})">
                &times;
            </button>
        `,u.appendChild(n),updateConditions(n.querySelector(".column-select")),A())}function q(t){return Array.from(u.querySelectorAll(".column-select")).map(l=>l.value).includes(t)}function A(){const t=u.children.length,n=Object.keys(s.columns).length;t>=n?(m.disabled=!0,m.classList.add("opacity-50","cursor-not-allowed")):(m.disabled=!1,m.classList.remove("opacity-50","cursor-not-allowed"))}window.updateConditions=function(t){const n=t.closest(".filter-row"),l=parseInt(n.dataset.filterId),i=t.value,r=s.columns[i].type;let d=n.querySelector(".value-input");o.get(l).column=i;const f=d.value;o.get(l).value=f;let a;r==="select"?(a=document.createElement("select"),a.className="value-input",a.innerHTML=`<option value="">Select value...</option>
                ${s.columns[i].options.map(x=>`<option value="${x}">${x}</option>`).join("")}`):(a=document.createElement("input"),a.className="value-input",a.placeholder="Enter value...",a.type=r==="date"?"date":r==="number"?"number":"text"),n.replaceChild(a,d),d=a;const h=o.get(l).value;h&&(d.value=h),d.addEventListener("input",()=>{o.get(l).value=d.value})},window.removeFilterRow=function(t){const n=document.querySelector(`[data-filter-id="${t}"]`);n&&(n.remove(),o.delete(t),A(),u.children.length===0&&g())};function j(){c=[],o.forEach((t,n)=>{const l=document.querySelector(`[data-filter-id="${n}"]`);if(!l)return;const i=l.querySelector(".column-select"),r=l.querySelector(".value-input"),d=i?i.value:"",f=r?r.value:"";d&&f&&c.push({column:d,value:f,label:`${s.columns[d].label} "${f}"`})}),y(),b(),k()}function H(){u.innerHTML="",o.clear(),c=[],y(),b(),g()}function P(){u.innerHTML="",o.clear(),c=[],y(),b(),g()}function y(){if(c.length===0){E.style.display="none";return}E.style.display="block",S.innerHTML=c.map((t,n)=>`
            <div class="active-filter-tag">
                ${t.label}
                <button class="remove-tag" onclick="removeActiveFilter(${n})">&times;</button>
            </div>
        `).join("")}window.removeActiveFilter=function(t){c.splice(t,1),y(),b()};function b(){const t=document.querySelectorAll("#myTable tbody tr.taskRow");let n=!1;t.forEach(l=>{let i=!0;c.length>0&&(i=c.every(a=>{const h=V(l,a.column);return a.column==="date"?h.split(" ")[0]===a.value:G(h,a.value)}));const r=T.value.toLowerCase().trim(),f=l.textContent.toLowerCase().includes(r);i=i&&f,i?(l.classList.remove("js-hidden"),n=!0):l.classList.add("js-hidden")}),R.style.display=n?"none":"flex"}function V(t,n){const l=z(n);if(l===-1)return"";const i=t.cells[l];return i?i.textContent.trim():""}function z(t){const n=document.querySelector("#myTable thead tr"),l=Array.from(n.cells),r={reference:"Reference",gds_reference:"GDS Reference",amadeus_reference:"Amadeus Reference",branch_name:"Branch Name",agent_name:"Agent Name",date:"Date",type:"Type",price:"Price",status:"Status",supplier:"Supplier"}[t];return l.findIndex(d=>d.textContent.trim()===r)}function G(t,n){const l=t.toLowerCase(),i=n.toLowerCase();return l.includes(i)}});
