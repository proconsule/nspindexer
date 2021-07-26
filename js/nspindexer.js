var titles = [];
var keywordTimer;
var contentUrl;

$(document).ready(function () {
    $("#keyword").val("");
    loadJson();
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
});

$("#startNetInstall").on('click', function () {
    startNetInstall();
})

function loadJson() {
    $.getJSON("index.php?json", function (data) {
        titles = data.titles;
        contentUrl = data.contentUrl;
        $('#version').text(data.version);
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
    activate();
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

function activate() {
    $('.contentListTrigger').on('click', function (event) {
        event.preventDefault();
        var target = $(this).siblings('.contentList')[0];
        $(target).slideToggle();
        var trigger = event.target;
        $(trigger).find('.listChevron').toggleClass("bi-chevron-down bi-chevron-up");
    });
    $('.btnNetInstall').on('click', function () {
        var titleId = $(this).data("title-id");
        modalNetInstall(titleId);
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
    var updateTemplate = $('#updateTemplate').html();
    $.each(title.updates, function (i, u) {
        listUpdates += tmpl(updateTemplate, {
            version: u.version,
            revision: u.version / 65536,
            name: u.path.split(/\//).pop(),
            url: contentUrl + u.path,
            size: bytesToHuman(u.size_real)
        });
    });
    var dlcTemplate = $('#dlcTemplate').html();
    $.each(title.dlc, function (i, d) {
        listDlc += tmpl(dlcTemplate, {
            name: d.path.split(/\//).pop(),
            url: contentUrl + d.path,
            size: bytesToHuman(d.size_real)
        });
    });
    var updateStatus = '<i class="bi-x-circle-fill text-danger"></i>';
    if (checkLatest(title.updates, title.latest_version)) {
        updateStatus = '<i class="bi-check-circle-fill text-success"></i>';
    }
    var countUpdates = Object.keys(title.updates).length;
    var countDlc = Object.keys(title.dlc).length;
    var cardTemplate = $('#cardTemplate');
    var card = tmpl(cardTemplate.html(), {
        thumbUrl: title.thumb,
        titleId: id,
        bannerUrl: title.banner,
        name: title.name,
        intro: title.intro,
        latestVersion: title.latest_version == null ? "?" : title.latest_version,
        latestDate: title.latest_date == null ? "?" : title.latest_date,
        updateStatus: updateStatus,
        fileName: title.path.split(/\//).pop(),
        fileUrl: contentUrl + title.path,
        fileSize: bytesToHuman(title.size_real),
        hideUpdates: (countUpdates == 0) ? "d-none" : "",
        countUpdates: countUpdates,
        listUpdates: listUpdates,
        hideDlc: (countDlc == 0) ? "d-none" : "",
        countDlc: countDlc,
        listDlc: listDlc
    });
    $('#titleList').append(card);
}


function startNetInstall() {
    var listFiles = [];
    var dstAddr = $("#netInstalldstAddr").val();
    $('.netInstallCheckbox:checked').each(function (i, e) {
        listFiles.push($(this).data('path'));
    });
    $.post("netinstall.php", {
        dstAddr: dstAddr,
        listFiles: listFiles
    }, function (status) {
        if(status.int === 0) {
            $('#modalNetInstall').modal('hide');
        } else {
            alert(status.msg);
        }
    });
}


function modalNetInstall(titleId) {

    $("#listNetInstall").empty();
    var contentTemplate = $('#netInstallContentTemplate').html();

    var countUpdates = Object.keys(titles[titleId].updates).length;
    var listUpdates = [];
    $.each(titles[titleId].updates, function (i, u) {
        listUpdates += tmpl(contentTemplate, {
            type: 'update',
            idx: i,
            name: u.version,
            path: contentUrl + u.path,
        });
    });

    var countDlc = Object.keys(titles[titleId].dlc).length;
    var listDlc = [];
    $.each(titles[titleId].dlc, function (i, u) {
        listDlc += tmpl(contentTemplate, {
            type: 'dlc',
            idx: i,
            name: u.path.split(/\//).pop(),
            path: contentUrl + u.path,
        });
    });

    var listTemplate = $('#netInstallTemplate').html();
    var list = tmpl(listTemplate, {
        path: encodeURI(titles[titleId]["path"]),
        name: titles[titleId]["path"].split(/\//).pop(),
        hideUpdates: (countUpdates == 0) ? "d-none" : "",
        listUpdates: listUpdates,
        hideDlc: (countDlc == 0) ? "d-none" : "",
        listDlc: listDlc
    });

    $("#listNetInstall").append(list);
    $('#modalNetInstall').modal('show');

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
