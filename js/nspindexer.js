var titles = [];
var keywordTimer;

$(document).ready(function () {
    $("#keyword").val("");
    loadJson()
})

$("#keyword").on('keydown', function (event) {
    if (event.keyCode == 13) {
        event.preventDefault();
    }
});

$("#keyword").on('keyup', function () {
    var keyword = $("#keyword").val().toLocaleLowerCase();
    if (keyword.length > 2) {
        clearInterval(keywordTimer);
        keywordTimer = setTimeout(function () {
            createRows(titles, keyword);
        }, 1000);
    } else if (keyword.length == 0) {
        createRows(titles);
    }
});

$("#keywordClear").on('click', function () {
    $('#keyword').val('');
    createRows(titles);
})

function loadJson() {
    $.getJSON("index.php?json", function (data) {
        titles = data;
        createRows(titles);
    }).done(function () {
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl)
        });
    })
}

function createRows(data, keyword = "") {
    $('#titleList').empty();
    $.each(data, function (id, title) {
        if (keyword.length > 0 && title.name.toLowerCase().search(keyword) == -1) {
            return true;
        }
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
    $('.contentListTrigger').on('click', function (event) {
        event.preventDefault();
        var target = $(this).siblings('.contentList')[0];
        $(target).slideToggle();
        var trigger = event.target;
        $(trigger).find('.listChevron').toggleClass("bi-chevron-down bi-chevron-up");
    });
}

// by mpen, https://stackoverflow.com/a/14919494/5218832
function bytesToHuman(bytes, si = false, dp = 1) {
    const thresh = si ? 1000 : 1024;
    if (Math.abs(bytes) < thresh) {
        return bytes + ' B';
    }
    const units = si
        ? ['kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']
        : ['KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
    let u = -1;
    const r = 10 ** dp;
    do {
        bytes /= thresh;
        ++u;
    } while (Math.round(Math.abs(bytes) * r) / r >= thresh && u < units.length - 1);
    return bytes.toFixed(dp) + ' ' + units[u];
}

function createCard(id, title) {
    var listUpdates = [];
    var listDlc = []
    $.each(title.updates, function (i, u) {
        listUpdates += '<li class="list-group-item"><strong>#' + u.version / 65536 + ' / v' + u.version + ':</strong> ' + u.path.split(/\//).pop() + ' <a href="' + contentUrl + u.path + '"><i class="bi-cloud-arrow-down-fill"></i></a><span class="badge bg-primary float-end">' + bytesToHuman(u.size_real) + '</span></li>';
    })
    $.each(title.dlc, function (i, d) {
        listDlc += '<li class="list-group-item">' + d.path.split(/\//).pop() + ' <a href="' + contentUrl + d.path + '"><i class="bi-cloud-arrow-down-fill"></i></a><span class="badge bg-primary float-end">' + bytesToHuman(d.size_real) + '</span></li>';
    })
    var updateStatus = '<i class="bi-x-circle-fill text-danger"></i>';
    if (checkLatest(title.updates, title.latest_version)) {
        updateStatus = '<i class="bi-check-circle-fill text-success"></i>';
    }
    var countUpdates = Object.keys(title.updates).length;
    var countDlc = Object.keys(title.dlc).length;
    var cardTemplate = $('#cardTemplate');
    var card = tmpl(cardTemplate.html(), {
        thumbUrl: title.thumb,
        bannerUrl: title.banner,
        name: title.name,
        intro: title.intro,
        latestVersion: title.latest_version == null ? "?" : title.latest_version,
        latestDate: title.latest_date == null ? "?" : title.latest_date,
        updateStatus: updateStatus,
        baseFilename: title.path.split(/\//).pop(),
        baseUrl: contentUrl + title.path,
        baseSize: bytesToHuman(title.size_real),
        hideUpdates: (countUpdates == 0) ? "d-none" : "",
        countUpdates: countUpdates,
        listUpdates: listUpdates,
        hideDlc: (countDlc == 0) ? "d-none" : "",
        countDlc: countDlc,
        listDlc: listDlc
    });
    $('#titleList').append(card);
}

// by John Resig, https://johnresig.com/blog/javascript-micro-templating/
(function () {
    var cache = {};
    this.tmpl = function tmpl(str, data) {
        // Figure out if we're getting a template, or if we need to
        // load the template - and be sure to cache the result.
        var fn = !/\W/.test(str) ?
            cache[str] = cache[str] ||
                tmpl(document.getElementById(str).innerHTML) :
            // Generate a reusable function that will serve as a template
            // generator (and which will be cached).
            new Function("obj",
                "var p=[],print=function(){p.push.apply(p,arguments);};" +
                // Introduce the data as local variables using with(){}
                "with(obj){p.push('" +
                // Convert the template into pure JavaScript
                str
                    .replace(/[\r\t\n]/g, " ")
                    .split("<%").join("\t")
                    .replace(/((^|%>)[^\t]*)'/g, "$1\r")
                    .replace(/\t=(.*?)%>/g, "',$1,'")
                    .split("\t").join("');")
                    .split("%>").join("p.push('")
                    .split("\r").join("\\'")
                + "');}return p.join('');");
        // Provide some basic currying to the user
        return data ? fn(data) : fn;
    };
})();