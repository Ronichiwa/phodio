<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../includes/booking_helpers.php';
checkLogin();

$packages = phodio_package_catalog();
$serviceTypes = phodio_service_types();
$statuses = phodio_booking_statuses();
$groupedPackages = [];
foreach ($packages as $package) {
    $groupedPackages[$package['category']][] = $package;
}
$clients = $conn->query('SELECT id, firstname, lastname, username FROM users ORDER BY firstname, lastname');
function admin_bookings_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings &amp; Service Progress | SOULPRINT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .workflow-banner{background:linear-gradient(135deg,rgba(59,130,246,.14),rgba(239,68,68,.08));border:1px solid #303849;border-radius:14px;padding:17px 20px}
        .admin-progress-timeline{border-left:1px solid #465064;margin-left:6px;padding-left:17px}
        .admin-progress-item{position:relative;padding-bottom:13px}
        .admin-progress-item:before{content:"";position:absolute;left:-22px;top:5px;width:9px;height:9px;border-radius:50%;background:#ef4444}
        .status-chip{display:inline-flex;border-radius:999px;padding:5px 10px;font-size:.75rem;font-weight:750}
        .status-pending{background:rgba(245,158,11,.14);color:#fbbf24}.status-confirmed{background:rgba(59,130,246,.15);color:#93c5fd}.status-progress,.status-editing{background:rgba(168,85,247,.16);color:#d8b4fe}.status-ready{background:rgba(16,185,129,.16);color:#6ee7b7}.status-completed{background:rgba(34,197,94,.16);color:#86efac}.status-cancelled{background:rgba(148,163,184,.14);color:#cbd5e1}
        .detail-table td{padding:.45rem .25rem;vertical-align:top}.detail-table td:first-child{color:#a0a0a0;width:35%}
        #calendar{min-height:650px;background:#1a1a1a;padding:12px}
        .fc{--fc-border-color:#303849;--fc-page-bg-color:#1a1a1a;--fc-neutral-bg-color:#171d29}
        .fc .fc-toolbar-title{color:#fff;font-size:1.15rem}.fc .fc-button-primary{background:#262e3e;border-color:#414d62}.fc .fc-button-primary:not(:disabled).fc-button-active{background:#ce0000;border-color:#ce0000}.fc .fc-daygrid-day-number,.fc .fc-col-header-cell-cushion{color:#fff;text-decoration:none}
        .progress-note{min-height:85px}
        @media(max-width:992px){#calendar{min-height:520px}}
    </style>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">
    <div class="container-fluid py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div><div class="text-uppercase small fw-bold text-info mb-1" style="letter-spacing:.12em">Central appointment scheduling</div><h1 class="h3 text-white fw-bold mb-1">Bookings &amp; Service Progress</h1><p class="text-muted mb-0">Manage appointment requests and post visible service-status updates for clients.</p></div>
            <button type="button" class="btn btn-primary" id="newBookingButton"><i class="ri-add-line me-1"></i>Schedule appointment</button>
        </div>
        <?php if (isset($_GET['status']) && $_GET['status'] === 'saved'): ?><div class="alert alert-success">Booking details saved.</div><?php endif; ?>
        <?php if (isset($_GET['error'])): ?><div class="alert alert-danger"><?= admin_bookings_h((string) $_GET['error']) ?></div><?php endif; ?>
        <div class="workflow-banner mb-4 text-white"><strong><i class="ri-information-line text-info me-2"></i>Client workflow</strong><span class="text-muted ms-2">New requests are held as Pending; update the status below as the session is confirmed, photographed, edited, and completed.</span></div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card shadow border-0">
                    <div class="card-header d-flex justify-content-between align-items-center text-white"><span><i class="ri-calendar-event-line me-2 text-info"></i>Studio schedule</span><span class="small text-muted">Select a booking to manage it</span></div>
                    <div id="calendar"></div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card shadow border-0">
                    <div class="card-header text-white"><i class="ri-radar-line me-2 text-info"></i>Booking inspector</div>
                    <div class="card-body" id="bookingInspector" aria-live="polite">
                        <div class="text-center py-5"><i class="ri-cursor-line ri-2x text-muted d-block mb-3"></i><p class="text-muted">Select a booking on the calendar to review details and update progress.</p></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content" id="bookingForm" action="process_booking.php" method="post">
            <div class="modal-header border-secondary"><div><div class="small text-info text-uppercase fw-bold" style="letter-spacing:.12em">Central schedule</div><h2 class="modal-title fs-5" id="modalTitle">Schedule an appointment</h2></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <input type="hidden" name="booking_id" id="bookingIdInput">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="clientSelect">Client account <span class="text-muted">(optional for walk-ins)</span></label><select class="form-select" name="client_id" id="clientSelect"><option value="">Walk-in / not linked</option><?php while ($client = $clients->fetch_assoc()): ?><option value="<?= (int) $client['id'] ?>"><?= admin_bookings_h(trim($client['firstname'] . ' ' . $client['lastname']) . ' · ' . $client['username']) ?></option><?php endwhile; ?></select></div>
                    <div class="col-md-6"><label class="form-label" for="sessionTitle">Session title / occasion</label><input class="form-control" type="text" name="title" id="sessionTitle" maxlength="255" required placeholder="e.g. Graduation portraits"></div>
                    <div class="col-md-6"><label class="form-label" for="serviceType">Session type</label><select class="form-select" name="service_type" id="serviceType" required><?php foreach ($serviceTypes as $key => $label): ?><option value="<?= admin_bookings_h($key) ?>"><?= admin_bookings_h($label) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label" for="attendeeCount">Number of people</label><select class="form-select" name="attendee_count" id="attendeeCount" required><option value="1">1 person</option><option value="2">2 people</option><option value="3">3 people</option><option value="4">4 people</option></select></div>
                    <div class="col-md-6"><label class="form-label" for="packageSelect">Package</label><select class="form-select" name="package_key" id="packageSelect" required><?php foreach ($groupedPackages as $category => $items): ?><optgroup label="<?= admin_bookings_h($category) ?>"><?php foreach ($items as $package): ?><option value="<?= admin_bookings_h($package['key']) ?>" data-price="<?= (int) $package['price'] ?>" data-min="<?= (int) $package['min_people'] ?>" data-max="<?= (int) $package['max_people'] ?>"><?= admin_bookings_h($package['name']) ?> — ₱<?= number_format($package['price']) ?> · <?= admin_bookings_h($package['duration']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label" for="packagePrice">Package price</label><input class="form-control" id="packagePrice" type="text" readonly></div>
                    <div class="col-md-6"><label class="form-label" for="motif">Theme / motif</label><input class="form-control" type="text" name="motif" id="motif" maxlength="100" placeholder="e.g. Vintage, minimalist, floral"></div>
                    <div class="col-md-3"><label class="form-label" for="bookingDate">Date</label><input class="form-control" type="date" name="date" id="bookingDate" min="<?= date('Y-m-d') ?>" required></div>
                    <div class="col-md-3"><label class="form-label" for="bookingPeriod">Period</label><select class="form-select" name="period" id="bookingPeriod" required><option value="AM">Morning · 9:00 AM</option><option value="PM">Afternoon · 1:00 PM</option></select></div>
                    <div class="col-12"><label class="form-label" for="clientNotes">Client requirements / notes</label><textarea class="form-control" name="client_notes" id="clientNotes" rows="3" maxlength="1000" placeholder="Any requirements for the studio team."></textarea></div>
                </div>
                <p class="small text-muted mt-3 mb-0">Only one morning and one afternoon appointment can be scheduled per date. Saving a new appointment sets it to Confirmed.</p>
            </div>
            <div class="modal-footer border-secondary"><button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary px-4">Save booking</button></div>
        </form>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:2000"><div class="toast text-bg-dark border-secondary" id="adminToast" role="status" aria-live="polite"><div class="d-flex"><div class="toast-body" id="adminToastMessage"></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script>
const bookingForm=document.getElementById('bookingForm');
const bookingModal=new bootstrap.Modal(document.getElementById('bookingModal'));
const adminToast=new bootstrap.Toast(document.getElementById('adminToast'),{delay:4000});
const inspector=document.getElementById('bookingInspector');
const packageSelect=document.getElementById('packageSelect');
const attendeeSelect=document.getElementById('attendeeCount');
const dateInput=document.getElementById('bookingDate');
let selectedBookingId=null;
let adminCalendar=null;

function escapeHtml(value){return String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));}
function notify(message){document.getElementById('adminToastMessage').textContent=message;adminToast.show();}
function resetForm(date=''){
    bookingForm.reset();
    document.getElementById('bookingIdInput').value='';
    document.getElementById('modalTitle').textContent='Schedule an appointment';
    dateInput.value=date;
    dateInput.min='<?= date('Y-m-d') ?>';
    document.getElementById('packagePrice').value='';
    refreshPackagePrice();
}
function refreshPackagePrice(){
    const option=packageSelect.selectedOptions[0];
    if(!option)return;
    document.getElementById('packagePrice').value='₱'+Number(option.dataset.price).toLocaleString();
    const count=Number(attendeeSelect.value),min=Number(option.dataset.min),max=Number(option.dataset.max);
    if(count<min||count>max)attendeeSelect.value=String(min);
}
function progressMarkup(updates){
    if(!updates||!updates.length)return '<p class="small text-muted mb-0">No progress updates have been posted yet.</p>';
    return updates.map(update=>`<div class="admin-progress-item"><div class="d-flex justify-content-between gap-2"><strong>${escapeHtml(update.status)}</strong><small class="text-muted">${escapeHtml(update.created_at)}</small></div><div class="small text-muted mt-1">${escapeHtml(update.note||'Progress updated by studio.')}</div></div>`).join('');
}
function renderBooking(booking){
    const classes={'Pending':'status-pending','Confirmed':'status-confirmed','In Progress':'status-progress','Editing':'status-editing','Ready for Pickup':'status-ready','Completed':'status-completed','Cancelled':'status-cancelled'};
    const statusClass=classes[booking.status]||'status-pending';
    const time=booking.period==='AM'?'9:00 AM · Morning':'1:00 PM · Afternoon';
    inspector.innerHTML=`
        <div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div><h3 class="h6 fw-bold mb-1">${escapeHtml(booking.title||booking.package_type)}</h3><div class="small text-muted">${escapeHtml(booking.client_name||'Walk-in / not linked')}</div></div><span class="status-chip ${statusClass}">${escapeHtml(booking.status)}</span></div>
        <table class="table table-borderless table-sm text-white detail-table mb-3">
            <tr><td>Session type</td><td>${escapeHtml(booking.service_type||'—')}</td></tr><tr><td>Package</td><td>${escapeHtml(booking.package_type||'—')}</td></tr>
            <tr><td>Price</td><td class="text-success fw-bold">₱${Number(booking.price||0).toLocaleString()}</td></tr><tr><td>Theme</td><td>${escapeHtml(booking.motif||'—')}</td></tr>
            <tr><td>Group size</td><td>${Number(booking.attendee_count||1)} ${Number(booking.attendee_count||1)===1?'person':'people'}</td></tr><tr><td>Schedule</td><td>${escapeHtml(booking.booking_date)} · ${time}</td></tr>
            ${booking.phone?`<tr><td>Phone</td><td>${escapeHtml(booking.phone)}</td></tr>`:''}${booking.username?`<tr><td>Email</td><td>${escapeHtml(booking.username)}</td></tr>`:''}
            ${booking.client_notes?`<tr><td>Client notes</td><td>${escapeHtml(booking.client_notes)}</td></tr>`:''}
        </table>
        <button type="button" class="btn btn-sm btn-outline-light w-100 mb-4" id="editSelectedBooking"><i class="ri-edit-line me-1"></i>Edit appointment details</button>
        <div class="fw-bold small mb-3"><i class="ri-git-commit-line text-info me-1"></i>Post a client-visible progress update</div>
        <form id="statusForm" action="update_booking_status.php" method="post">
            <input type="hidden" name="booking_id" value="${Number(booking.id)}">
            <label class="form-label small" for="progressStatus">Service status</label>
            <select class="form-select form-select-sm mb-2" name="status" id="progressStatus" required>
                <?php foreach ($statuses as $status): ?><option value="<?= admin_bookings_h($status) ?>"><?= admin_bookings_h($status) ?></option><?php endforeach; ?>
            </select>
            <label class="form-label small" for="progressNote">Progress note</label>
            <textarea class="form-control form-control-sm progress-note mb-2" id="progressNote" name="status_note" maxlength="1500" placeholder="Share a status update or next step with the client."></textarea>
            <button class="btn btn-primary btn-sm w-100" type="submit"><i class="ri-send-plane-line me-1"></i>Save status update</button>
        </form>
        <div class="fw-bold small mt-4 mb-3"><i class="ri-history-line text-info me-1"></i>Update history</div><div class="admin-progress-timeline">${progressMarkup(booking.updates)}</div>`;
    document.getElementById('progressStatus').value=booking.status;
    document.getElementById('editSelectedBooking').addEventListener('click',()=>openEditModal(booking));
    document.getElementById('statusForm').addEventListener('submit',saveProgressUpdate);
}
async function fetchDetails(id,silent=false){
    try{
        const response=await fetch('get_booking_details.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({id:String(id)})});
        const data=await response.json();
        if(!response.ok||!data.ok)throw new Error(data.message||'Could not load this booking.');
        selectedBookingId=Number(data.booking.id);
        renderBooking(data.booking);
    }catch(error){if(!silent)notify(error.message);}
}
function openEditModal(booking){
    resetForm(booking.booking_date);
    document.getElementById('modalTitle').textContent='Edit appointment details';
    document.getElementById('bookingIdInput').value=booking.id;
    document.getElementById('clientSelect').value=booking.client_id||'';
    document.getElementById('sessionTitle').value=booking.title||'';
    document.getElementById('serviceType').value=booking.service_type||'portrait';
    attendeeSelect.value=String(booking.attendee_count||1);
    if(booking.package_key&&packageSelect.querySelector(`option[value="${CSS.escape(booking.package_key)}"]`))packageSelect.value=booking.package_key;
    document.getElementById('motif').value=booking.motif||'';
    document.getElementById('clientNotes').value=booking.client_notes||'';
    document.getElementById('bookingPeriod').value=booking.period||'AM';
    refreshPackagePrice();
    bookingModal.show();
}
async function saveProgressUpdate(event){
    event.preventDefault();
    const form=event.currentTarget;
    const button=form.querySelector('button[type="submit"]');
    button.disabled=true;
    try{
        const response=await fetch(form.action,{method:'POST',body:new FormData(form)});
        const data=await response.json();
        if(!response.ok||!data.ok)throw new Error(data.message||'Could not update this booking.');
        notify(data.message);
        adminCalendar.refetchEvents();
        await fetchDetails(selectedBookingId,true);
    }catch(error){notify(error.message);}finally{button.disabled=false;}
}

adminCalendar=new FullCalendar.Calendar(document.getElementById('calendar'),{
    initialView:'dayGridMonth',height:'auto',events:'load_events.php',
    headerToolbar:{left:'prev,next today',center:'title',right:'dayGridMonth,timeGridWeek'},
    eventTimeFormat:{hour:'numeric',minute:'2-digit',meridiem:'short'},
    eventClick(info){fetchDetails(info.event.id);},
    dateClick(info){resetForm(info.dateStr.slice(0,10));bookingModal.show();}
});
adminCalendar.render();
document.getElementById('newBookingButton').addEventListener('click',()=>{resetForm('');bookingModal.show();});
packageSelect.addEventListener('change',refreshPackagePrice);
attendeeSelect.addEventListener('change',refreshPackagePrice);
refreshPackagePrice();
bookingForm.addEventListener('submit',event=>{
    const option=packageSelect.selectedOptions[0];
    const count=Number(attendeeSelect.value);
    if(option&&(count<Number(option.dataset.min)||count>Number(option.dataset.max))){
        event.preventDefault();notify('Choose a package that supports the selected group size.');
    }
});
</script>
</body>
</html>
