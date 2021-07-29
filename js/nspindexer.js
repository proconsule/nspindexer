var titles = [];
var keywordTimer;
var contentUrl;
var netInstallEnabled = false;

$(document).ready(function () {
    $("#keyword").val("");
    loadConfig();
    loadTitles();
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

function loadConfig() {
    $.getJSON("index.php?config", function (data) {
        contentUrl = data.contentUrl;
        $('#version').text(data.version);
        $('#switchIp').val(data.switchIp);
        netInstallEnabled = data.enableNetInstall;
    });
}

function loadTitles() {
    $.getJSON("index.php?titles", function (data) {
        titles = data.titles;
        createRows(titles);
    }).done(function () {
        init();
    });
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
}

function lazyLoad() {
    $('.lazy').Lazy({
        visibleOnly: true,
        bind: 'event',
        scrollDirection: 'vertical',
        effect: 'fadeIn',
        effectTime: 500
    });
}

function enableListTriggers() {
    $('.contentListTrigger').on('click', function (event) {
        event.preventDefault();
        var target = $(this).siblings('.contentList')[0];
        $(target).slideToggle();
        var trigger = event.target;
        $(trigger).find('.listChevron').toggleClass("bi-chevron-down bi-chevron-up");
    });
}

function enableNetInstall() {
    $('.btnNetInstall').on('click', function () {
        var titleId = $(this).data("title-id");
        modalNetInstall(titleId);
    });
}

function enableAnalyze() {
    $('.btnAnalyze').on('click', function () {
        $.getJSON("index.php?parsensp=" + encodeURIComponent($(this).data('path')), function (data) {
            console.log(data);
            if (data.int === 0) {
                alert("TitleId: " + data.titleId + ", Version: " + data.version);
            } else {
                alert(data.msg);
            }
        }).done(function () {
            // foo
        });
    });
}

function enablePopovers() {
    $('[data-bs-toggle="tooltip"]').each(function () {
        new bootstrap.Tooltip($(this), {
            container: $(this).parent()
        });
    })
}

function init() {
    lazyLoad();
    enableListTriggers();
    enableNetInstall();
    enableAnalyze();
    enablePopovers();
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
    $.each(title.updates, function (version, u) {
        listUpdates += tmpl(updateTemplate, {
            version: version,
            revision: version / 65536,
            date: u.date,
            url: encodeURI(contentUrl + '/' + u.path),
            path: u.path,
            size: bytesToHuman(u.size_real)
        });
    });
    var dlcTemplate = $('#dlcTemplate').html();
    $.each(title.dlc, function (i, d) {
        listDlc += tmpl(dlcTemplate, {
            name: d.name,
            url: encodeURI(contentUrl + '/' + d.path),
            path: d.path,
            size: bytesToHuman(d.size_real)
        });
    });
    var updateStatus = '<i class="bi-x-circle-fill text-danger"></i>';
    if (title.latest_version === 0 || title.latest_version in title.updates) {
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
        enableNetInstall: (netInstallEnabled) ? "" : "d-none",
        latestVersion: title.latest_version == null ? "?" : title.latest_version,
        latestDate: title.latest_date == null ? "?" : title.latest_date,
        updateStatus: updateStatus,
        filePath: encodeURI(title.path),
        fileUrl: contentUrl + '/' + title.path,
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
    var dstAddr = $("#switchIp").val();
    $('.netInstallCheckbox:checked').each(function (i, e) {
        listFiles.push($(this).data('path'));
    });
    if (listFiles.length > 0) {
        $.post("netinstall.php", {
            dstAddr: dstAddr,
            listFiles: listFiles
        }, function (status) {
            if (status.int === 0) {
                $('#modalNetInstall').modal('hide');
            } else {
                alert(status.msg);
            }
        });
    } else {
        alert("Nothing to install");
    }
}


function modalNetInstall(titleId) {
    $("#startNetInstall").attr('disabled', true);
    $("#listNetInstall").empty();
    var contentTemplate = $('#netInstallContentTemplate').html();

    var countUpdates = Object.keys(titles[titleId].updates).length;
    var listUpdates = [];
    $.each(titles[titleId].updates, function (version, u) {
        listUpdates += tmpl(contentTemplate, {
            idx: version,
            type: 'update',
            name: 'v' + version + ' <small class="text-muted">(#' + version / 65536 + ', ' + u.date + ')</small>',
            path: u.path,
            size_real: bytesToHuman(u.size_real)
        });
    });

    var countDlc = Object.keys(titles[titleId].dlc).length;
    var listDlc = [];
    $.each(titles[titleId].dlc, function (i, d) {
        listDlc += tmpl(contentTemplate, {
            type: 'dlc',
            idx: i,
            name: d.name,
            path: d.path,
            size_real: bytesToHuman(d.size_real)
        });
    });

    var listTemplate = $('#netInstallTemplate').html();
    var list = tmpl(listTemplate, {
        path: encodeURI(titles[titleId].path),
        name: titles[titleId].name,
        hideUpdates: (countUpdates == 0) ? "d-none" : "",
        listUpdates: listUpdates,
        hideDlc: (countDlc == 0) ? "d-none" : "",
        listDlc: listDlc,
        size_real: bytesToHuman(titles[titleId].size_real)
    });

    $("#listNetInstall").append(list);
    $(".netInstallCheckbox").on('click', function () {
        var checked = $('.netInstallCheckbox:checked');
        var btnStart = $("#startNetInstall");
        if (checked.length > 0) {
            btnStart.attr("disabled", false);
        } else {
            btnStart.attr("disabled", true);
        }

    })
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
