jQuery(document).ready(function() {
    jQuery("#since").datepicker();
    jQuery("#since").datepicker("option", "dateFormat", "yy-mm-dd");
    jQuery("#until").datepicker();
    jQuery("#until").datepicker("option", "dateFormat", "yy-mm-dd");
});
