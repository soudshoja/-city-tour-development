document.querySelector(".dataTable-bottom");document.querySelector(".dataTable-pagination-list");document.getElementById("prevPage");document.getElementById("nextPage");const C=document.getElementById("myTable"),I=Array.from(C.querySelector("tbody").rows);Math.ceil(I.length/10);const T=document.querySelectorAll(".viewTask");T.forEach(s=>{s.addEventListener("click",function(i){i.preventDefault();const e=this.getAttribute("data-task-id"),t=this.getAttribute("data-task-url");q(e,t)})});function q(s,i){if(currentlyDisplayed===`task-${s}`){R();return}P(),fetch(i).then(e=>{if(!e.ok)throw new Error(`Failed to fetch task details: ${e.status}`);return e.json()}).then(e=>{var t,c,r,f,d,g,h,u,p,m,v,b,k,y,A,w,_,x,$,N,D,L,E;e&&e.client_name?(e.type,e.type==="flight"?`${e.country_from}${e.country_to}`:e.hotel_name,console.log("data : ",e),taskDetailsDiv.innerHTML=`
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
            <i class="fas fa-warehouse"></i> ${((t=e.supplier)==null?void 0:t.name)||"N/A"}
        </h3>
    </div>

    <div class="section status">
        <h4><i class="fas fa-info-circle blue-icon"></i> Status: <span class="status-label">${e.status}</span></h4>
        <div class="info-row">
            <p><i class="fas fa-map-marker-alt blue-icon"></i> <strong>Branch:</strong> ${((r=(c=e.agent)==null?void 0:c.branch)==null?void 0:r.name)||"N/A"}</p>
            <p><i class="fas fa-user-tie blue-icon"></i> <strong>Agent:</strong> ${((f=e.agent)==null?void 0:f.name)||"N/A"}</p>
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
            <p><i class="fas fa-plane-departure blue-icon"></i> <strong>Departure:</strong> ${((d=e.flight_details)==null?void 0:d.airport_from)||"N/A"} - ${((g=e.flight_details)==null?void 0:g.departure_time)||"N/A"}</p>
            <p><i class="fas fa-plane-arrival blue-icon"></i> <strong>Arrival:</strong> ${((h=e.flight_details)==null?void 0:h.airport_to)||"N/A"} - ${((u=e.flight_details)==null?void 0:u.arrival_time)||"N/A"}</p>
            <p><i class="fas fa-ticket-alt blue-icon"></i> <strong>Flight:</strong> ${((p=e.flight_details)==null?void 0:p.flight_number)||"N/A"} - ${((m=e.flight_details)==null?void 0:m.class_type)||"N/A"}</p>
            <p><i class="fas fa-suitcase blue-icon"></i> <strong>Baggage:</strong> ${((v=e.flight_details)==null?void 0:v.baggage_allowed)||"N/A"}</p>
        </div>
    </div>`:`
    <div class="section hotel-details">
        <h4><i class="fas fa-hotel blue-icon"></i> Hotel Details</h4>
        <div class="info-row">
            <p><i class="fas fa-building blue-icon"></i> <strong>Hotel:</strong> ${e.hotel_name||"N/A"}</p>
            <p><i class="fas fa-map-marker-alt blue-icon"></i> <strong>Location:</strong> ${((k=(b=e.hotel_details)==null?void 0:b.hotel)==null?void 0:k.address)||"N/A"}, ${((A=(y=e.hotel_details)==null?void 0:y.hotel)==null?void 0:A.city)||"N/A"}</p>
            <p><i class="fas fa-globe blue-icon"></i> <strong>Country:</strong> ${e.hotel_country||"N/A"}</p>
            <p><i class="fas fa-calendar-check blue-icon"></i> <strong>Check-in:</strong> ${((w=e.hotel_details)==null?void 0:w.check_in)||"N/A"}</p>
            <p><i class="fas fa-calendar-times blue-icon"></i> <strong>Check-out:</strong> ${((_=e.hotel_details)==null?void 0:_.check_out)||"N/A"}</p>
            <p><i class="fas fa-star blue-icon"></i> <strong>Rating:</strong> ${(($=(x=e.hotel_details)==null?void 0:x.hotel)==null?void 0:$.rating)||"N/A"}</p>
            <p><i class="fas fa-star blue-icon"></i> <strong>Room Reference:</strong> ${((D=(N=e.hotel_details)==null?void 0:N.hotel)==null?void 0:D.room_reference)||"N/A"}</p>
            <p><i class="fas fa-bed blue-icon"></i> <strong>Room:</strong> ${((L=e.hotel_details)==null?void 0:L.room_type)||"N/A"} - ${((E=e.hotel_details)==null?void 0:E.room_number)||"N/A"}</p>
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

`,taskDetailsDiv.style.display="block",showRightDiv.classList.remove("hidden"),currentlyDisplayed=`task-${s}`):console.warn("Invalid Data:",e)}).catch(e=>{console.error("Error fetching task details:",e)})}const B=document.querySelector(".content-70");function P(s){B.classList.add("shrink"),showRightDiv.classList.add("visible"),taskDetailsDiv.style.display="block"}function R(){currentlyDisplayed=null,B.classList.remove("shrink"),showRightDiv.classList.remove("visible"),taskDetailsDiv.style.display="none"}const l=document.getElementById("floatingActions"),z=document.getElementById("closeTaskFloatingActions"),o=document.getElementById("selectAll"),n=document.querySelectorAll(".rowCheckbox"),S=document.getElementById("createInvoiceBtn");o&&o.addEventListener("change",function(){n.forEach(s=>s.checked=o.checked),a()});const a=()=>{const s=Array.from(n).some(i=>i.checked);S.disabled=!s};n.forEach(s=>{s.addEventListener("change",function(){const i=Array.from(n).every(t=>t.checked);o.checked=i,a(),Array.from(n).some(t=>t.checked)?l.classList.remove("hidden"):l.classList.add("hidden")})});a();S.addEventListener("click",function(){const s=Array.from(n).filter(t=>t.checked).map(t=>t.value);if(s.length===0){alert("No tasks selected!");return}console.log(s);const e=this.getAttribute("data-route")+"?task_ids="+s.join(",");window.location.href=e});z.addEventListener("click",function(){l.classList.add("hidden")});document.addEventListener("DOMContentLoaded",function(){});
