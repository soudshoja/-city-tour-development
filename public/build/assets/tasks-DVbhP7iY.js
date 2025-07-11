document.querySelector(".dataTable-bottom");document.querySelector(".dataTable-pagination-list");document.getElementById("prevPage");document.getElementById("nextPage");const U=document.getElementById("myTable"),J=Array.from(U.querySelector("tbody").rows);Math.ceil(J.length/10);const K=document.querySelectorAll(".viewTask");K.forEach(l=>{l.addEventListener("click",function(c){c.preventDefault();const e=this.getAttribute("data-task-id"),s=this.getAttribute("data-task-url");Q(e,s)})});function Q(l,c){if(currentlyDisplayed===`task-${l}`){Y();return}X(),fetch(c).then(e=>{if(!e.ok)throw new Error(`Failed to fetch task details: ${e.status}`);return e.json()}).then(e=>{var s,h,p,v,d,u,C,y,$,I,B,k,F,m,N,S,w,g,T,A,D,R,M;e&&e.client_name?(e.type,e.type==="flight"?`${e.country_from}${e.country_to}`:e.hotel_name,console.log("data : ",e),taskDetailsDiv.innerHTML=`
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
            <p><i class="fas fa-map-marker-alt blue-icon"></i> <strong>Branch:</strong> ${((p=(h=e.agent)==null?void 0:h.branch)==null?void 0:p.name)||"N/A"}</p>
            <p><i class="fas fa-user-tie blue-icon"></i> <strong>Agent:</strong> ${((v=e.agent)==null?void 0:v.name)||"N/A"}</p>
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
            <p><i class="fas fa-plane-departure blue-icon"></i> <strong>Departure:</strong> ${((d=e.flight_details)==null?void 0:d.airport_from)||"N/A"} - ${((u=e.flight_details)==null?void 0:u.departure_time)||"N/A"}</p>
            <p><i class="fas fa-plane-arrival blue-icon"></i> <strong>Arrival:</strong> ${((C=e.flight_details)==null?void 0:C.airport_to)||"N/A"} - ${((y=e.flight_details)==null?void 0:y.arrival_time)||"N/A"}</p>
            <p><i class="fas fa-ticket-alt blue-icon"></i> <strong>Flight:</strong> ${(($=e.flight_details)==null?void 0:$.flight_number)||"N/A"} - ${((I=e.flight_details)==null?void 0:I.class_type)||"N/A"}</p>
            <p><i class="fas fa-suitcase blue-icon"></i> <strong>Baggage:</strong> ${((B=e.flight_details)==null?void 0:B.baggage_allowed)||"N/A"}</p>
        </div>
    </div>`:`
    <div class="section hotel-details">
        <h4><i class="fas fa-hotel blue-icon"></i> Hotel Details</h4>
        <div class="info-row">
            <p><i class="fas fa-building blue-icon"></i> <strong>Hotel:</strong> ${e.hotel_name||"N/A"}</p>
            <p><i class="fas fa-map-marker-alt blue-icon"></i> <strong>Location:</strong> ${((F=(k=e.hotel_details)==null?void 0:k.hotel)==null?void 0:F.address)||"N/A"}, ${((N=(m=e.hotel_details)==null?void 0:m.hotel)==null?void 0:N.city)||"N/A"}</p>
            <p><i class="fas fa-globe blue-icon"></i> <strong>Country:</strong> ${e.hotel_country||"N/A"}</p>
            <p><i class="fas fa-calendar-check blue-icon"></i> <strong>Check-in:</strong> ${((S=e.hotel_details)==null?void 0:S.check_in)||"N/A"}</p>
            <p><i class="fas fa-calendar-times blue-icon"></i> <strong>Check-out:</strong> ${((w=e.hotel_details)==null?void 0:w.check_out)||"N/A"}</p>
            <p><i class="fas fa-star blue-icon"></i> <strong>Rating:</strong> ${((T=(g=e.hotel_details)==null?void 0:g.hotel)==null?void 0:T.rating)||"N/A"}</p>
            <p><i class="fas fa-star blue-icon"></i> <strong>Room Reference:</strong> ${((D=(A=e.hotel_details)==null?void 0:A.hotel)==null?void 0:D.room_reference)||"N/A"}</p>
            <p><i class="fas fa-bed blue-icon"></i> <strong>Room:</strong> ${((R=e.hotel_details)==null?void 0:R.room_type)||"N/A"} - ${((M=e.hotel_details)==null?void 0:M.room_number)||"N/A"}</p>
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

`,taskDetailsDiv.style.display="block",showRightDiv.classList.remove("hidden"),currentlyDisplayed=`task-${l}`):console.warn("Invalid Data:",e)}).catch(e=>{console.error("Error fetching task details:",e)})}const j=document.querySelector(".content-70");function X(l){j.classList.add("shrink"),showRightDiv.classList.add("visible"),taskDetailsDiv.style.display="block"}function Y(){currentlyDisplayed=null,j.classList.remove("shrink"),showRightDiv.classList.remove("visible"),taskDetailsDiv.style.display="none"}const V=document.getElementById("floatingActions"),Z=document.getElementById("closeTaskFloatingActions"),H=document.getElementById("selectAll"),L=document.querySelectorAll(".rowCheckbox"),z=document.getElementById("createInvoiceBtn");H.addEventListener("change",function(){L.forEach(l=>l.checked=H.checked),P()});const P=()=>{const l=Array.from(L).some(c=>c.checked);z.disabled=!l};L.forEach(l=>{l.addEventListener("change",function(){const c=Array.from(L).every(s=>s.checked);H.checked=c,P(),Array.from(L).some(s=>s.checked)?V.classList.remove("hidden"):V.classList.add("hidden")})});P();z.addEventListener("click",function(){const l=Array.from(L).filter(s=>s.checked).map(s=>s.value);if(l.length===0){alert("No tasks selected!");return}console.log(l);const e=this.getAttribute("data-route")+"?task_ids="+l.join(",");window.location.href=e});Z.addEventListener("click",function(){V.classList.add("hidden")});document.addEventListener("DOMContentLoaded",function(){const l=document.getElementById("searchInput"),c=document.getElementById("searchButton"),e=document.querySelectorAll("#myTable tbody tr.taskRow"),s=document.getElementById("noTasksFound"),h=document.getElementById("loadMoreWrapper"),p=l.cloneNode(!0);l.parentNode.replaceChild(p,l),c.addEventListener("click",function(){const v=p.value.toLowerCase().trim();let d=!1;e.forEach(u=>{const y=u.textContent.toLowerCase().includes(v);u.style.display=y?"":"none",y&&(d=!0)}),s&&s.classList.toggle("hidden",d),h&&h.classList.toggle("hidden",v.length>0)})});document.addEventListener("DOMContentLoaded",function(){const l={columns:{reference:{label:"Reference",type:"text"},gds_reference:{label:"GDS Reference",type:"text"},amadeus_reference:{label:"Amadeus Reference",type:"text"},branch_name:{label:"Branch Name",type:"text"},agent_name:{label:"Agent Name",type:"text"},date:{label:"Date",type:"date"},type:{label:"Type",type:"select",options:["hotel","flight"]},price:{label:"Price",type:"number"},status:{label:"Status",type:"select",options:["issued","refund","reissued","void","ticketed","confirmed"]},supplier:{label:"Supplier",type:"text"}}};let c=[],e=0;const s=new Map,h=document.getElementById("toggleFilters"),p=document.getElementById("filterModal"),v=document.getElementById("closeFilterModal"),d=document.getElementById("filterContainer"),u=document.getElementById("addFilterRow"),C=document.getElementById("applyFilters"),y=document.getElementById("clearAllFilters"),$=document.getElementById("activeFiltersContainer"),I=document.getElementById("activeFiltersList"),B=document.getElementById("clearAllActiveFilters"),k=document.getElementById("searchInput"),F=document.getElementById("noTasksFound"),m=document.getElementById("loadMoreWrapper"),N=m?m.querySelector("button"):null;h.addEventListener("click",S),v.addEventListener("click",w),u.addEventListener("click",g),C.addEventListener("click",D),y.addEventListener("click",R),B.addEventListener("click",M),p.addEventListener("click",function(t){t.target===p&&w()});function S(){p.classList.add("active"),d.children.length===0&&g(),A()}function w(){p.classList.remove("active")}function g(){const t=++e,n=document.createElement("div");n.className="filter-row",n.dataset.filterId=t,s.set(t,{column:"",value:""});const o=Object.entries(l.columns).filter(([i])=>!T(i));o.length!==0&&(n.innerHTML=`
            <select class="column-select w-48" onchange="updateConditions(this)">
                ${o.map(([i,a])=>`<option value="${i}" data-type="${a.type}">${a.label}</option>`).join("")}
            </select>
            <input type="text" class="value-input" placeholder="Enter value...">
            <button type="button" class="remove-filter-btn" onclick="removeFilterRow(${t})">
                &times;
            </button>
        `,d.appendChild(n),updateConditions(n.querySelector(".column-select")),A())}function T(t){return Array.from(d.querySelectorAll(".column-select")).map(o=>o.value).includes(t)}function A(){const t=d.children.length,n=Object.keys(l.columns).length;t>=n?(u.disabled=!0,u.classList.add("opacity-50","cursor-not-allowed")):(u.disabled=!1,u.classList.remove("opacity-50","cursor-not-allowed"))}window.updateConditions=function(t){const n=t.closest(".filter-row"),o=parseInt(n.dataset.filterId),i=t.value,a=l.columns[i].type;let r=n.querySelector(".value-input");s.get(o).column=i;const E=r.value;s.get(o).value=E;let f;a==="select"?(f=document.createElement("select"),f.className="value-input",f.innerHTML=`<option value="">Select value...</option>
                ${l.columns[i].options.map(_=>`<option value="${_}">${_}</option>`).join("")}`):(f=document.createElement("input"),f.className="value-input",f.placeholder="Enter value...",f.type=a==="date"?"date":a==="number"?"number":"text"),n.replaceChild(f,r),r=f;const b=s.get(o).value;b&&(r.value=b),r.addEventListener("input",()=>{s.get(o).value=r.value})},window.removeFilterRow=function(t){const n=document.querySelector(`[data-filter-id="${t}"]`);n&&(n.remove(),s.delete(t),A(),d.children.length===0&&g())};function D(){c=[],s.forEach((t,n)=>{const o=document.querySelector(`[data-filter-id="${n}"]`);if(!o)return;const i=o.querySelector(".column-select"),a=o.querySelector(".value-input"),r=i?i.value:"",E=a?a.value:"";r&&E&&c.push({column:r,value:E,label:`${l.columns[r].label} "${E}"`})}),q(),x(),w()}function R(){d.innerHTML="",s.clear(),c=[],q(),x(),g()}function M(){d.innerHTML="",s.clear(),c=[],q(),x(),g()}function q(){if(c.length===0){$.style.display="none";return}$.style.display="block",I.innerHTML=c.map((t,n)=>`
            <div class="active-filter-tag">
                ${t.label}
                <button class="remove-tag" onclick="removeActiveFilter(${n})">&times;</button>
            </div>
        `).join("")}window.removeActiveFilter=function(t){c.splice(t,1),q(),x()};function x(){const t=document.querySelectorAll("#myTable tbody tr.taskRow");let n=!1,o=0;t.forEach(i=>{let a=!0;c.length>0&&(a=c.every(b=>{const _=O(i,b.column);return b.column==="date"?_.split(" ")[0]===b.value:G(_,b.value)}));const r=k.value.toLowerCase().trim(),f=i.textContent.toLowerCase().includes(r);a=a&&f,i.style.display=a?"":"none",a&&(n=!0,o++)}),F.style.display=n?"none":"flex",m&&N&&(document.querySelectorAll("#myTable tbody tr.taskRow").length,Array.from(t).filter(a=>a.style.display!=="none").length<o?m.style.display="block":m.style.display="none")}function O(t,n){const o=W(n);if(o===-1)return"";const i=t.cells[o];return i?i.textContent.trim():""}function W(t){const n=document.querySelector("#myTable thead tr"),o=Array.from(n.cells),a={reference:"Reference",gds_reference:"GDS Reference",amadeus_reference:"Amadeus Reference",branch_name:"Branch Name",agent_name:"Agent Name",date:"Date",type:"Type",price:"Price",status:"Status",supplier:"Supplier"}[t];return o.findIndex(r=>r.textContent.trim()===a)}function G(t,n){const o=t.toLowerCase(),i=n.toLowerCase();return o.includes(i)}k&&k.addEventListener("click",function(){x()}),x()});
