$(document).ready(function () {
    loadJson()
})

function loadJson() {
    $.getJSON("index.php?json", function (data) {
        createRows(data);
    })
}

function createRows(data) {
    $.each(data, function (id, title) {
        createCard(id, title);
    });
}

function createCard(id, title) {
    var card = $(`
<div class="card mb-3 shadow-sm">
    <div class="row g-0">
        <div class="col-md-4">
            <img src="` + title.thumb + `" class="img-fluid rounded-start"/>
        </div>
        <div class="col-md-8">
            <div class="card-body">
                <h5 class="card-title">` + title.name + `</h5>
                <p class="card-text">
                    ` + title.intro + `
                </p>
                <p class="card-text">
                    <small class="text-muted">Last updated 3 mins ago</small>
                </p>
            </div>
        </div>
    </div>
</div>`);
    $('#titleList').append(card);
}