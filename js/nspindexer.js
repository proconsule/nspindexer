$(document).ready(function () {
    loadJson()
})

function loadJson() {
    $.getJSON("index.php?json", function (data) {
        createRows(data);
    }).done(function () {
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl)
        });
    })
}

function createRows(data) {
    $.each(data, function (id, title) {
        createCard(id, title);
        //return false;
    });
    lazyLoad();
    enableLists();
}

function checkLatest(updates, version) {
    for (const update in updates) {
        if (updates[update].version === version) {
            return true;
        }
    }
    return false;
}

function lazyLoad() {
    $('.lazy').Lazy({
        scrollDirection: 'vertical',
        effect: 'fadeIn',
        effectTime: 500,
        threshold: 100
    });
}

function enableLists() {
    $('.contentListTrigger').on('click',function(event)
    {
        event.preventDefault();
        var target = $(this).siblings('.contentList')[0];
        $(target).slideToggle();
        var trigger = event.target;
        $(trigger).find('.listChevron').toggleClass("bi-chevron-down bi-chevron-up");
    });
}

function createCard(id, title) {
    var updates = [];
    var dlc = []
    $.each(title.updates, function (i, u) {
        updates += '<li class="list-group-item">#' + u.version / 65536 + ' / v' + u.version + ': <a href="' + u.path + '">' + u.path.split(/\//).pop() + '</a></li>';
    })
    $.each(title.dlc, function (i, d) {
        dlc += '<li class="list-group-item dlcItem"><a href="' + d.path + '" data-bs-toggle="popover" data-bs-placement="left" data-bs-trigger="hover" data-bs-content="' + d.path.split(/\//).pop() + '">' + d.path.split(/\//).pop() + '</a></li>';
    })
    var updateStatus = '<i class="bi-x-circle-fill text-danger"></i>';
    if (checkLatest(title.updates, title.latest_version)) {
        updateStatus = '<i class="bi-check-circle-fill text-success"></i>';
    }
    var card = $(`
<div class="row gx-2 mb-4">
    <div class="col col-2">
        <div class="card px-0 shadow-sm fill cardThumb">
            <img data-src="` + title.thumb + `" class="img-fluid lazy"/>
        </div>
    </div>
    <div class="col col-10">
        <div class="card shadow-sm">
            <div class="cardBanner fill rounded-3">
              <img data-src="` + title.banner + `" class="img-fluid h-100 lazy">
            </div>
            <div class="card-body rounded cardBody">   
                <h5 class="card-title"><strong>` + title.name + `</strong></h5>
                <div class="card-text">
                    <p class="small titleIntro">` + title.intro + `</p>
                    <p class="small"><strong>Latest Version:</strong> v` + title.latest_version + ` (` + title.latest_date + `)
                        ` + updateStatus + `
                    </p>
                    <ul class="list-group">
                        <li class="list-group-item">
                            <p class="my-1">
                                <strong>Base Game: <a href="` + contentUrl + title.path + `">` + title.path.split(/\//).pop() + `</a></strong>
                                <a href="` + contentUrl + title.path + `">
                                    <i class="bi-cloud-arrow-down-fill"></i>
                                </a>
                            </p>
                        </li>
                        <li class="list-group-item">
                            <p class="my-1 contentListTrigger">
                                <strong>Updates</strong> 
                                <span class="badge rounded-pill bg-secondary">` + Object.keys(title.updates).length + `</span>
                                <i class="listChevron bi-chevron-down text-dark float-end"></i>
                            </p>
                            <ul class="list-group my-2 contentList">` + updates + `</ul>
                        </li>
                        <li class="list-group-item">
                            <p class="my-1 contentListTrigger">
                                <strong>DLC</strong> 
                                <span class="badge rounded-pill bg-secondary">` + Object.keys(title.dlc).length + `</span>
                                <i class="listChevron bi-chevron-down text-dark float-end"></i>
                            </p>
                            <ul class="list-group my-2 contentList">` + dlc + `</ul>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
`);
    $('#titleList').append(card);
}