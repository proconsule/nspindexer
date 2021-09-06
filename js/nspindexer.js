var titles = [];
var keywordTimer;
var contentUrl;
var netInstallEnabled = false;
var renameEnabled = false;
var romInfoEnabled = false;

var langFlags = ["🇺🇸","🇬🇧","🇯🇵","🇫🇷","🇩🇪","🇪🇸","🇪🇸","🇮🇹","🇩🇪","🇨🇦","🇵🇹","🇷🇺","🇰🇷","🇨🇳","🇨🇳"];

$(document).ready(function () {
    $("#keyword").val("");
    loadConfig();
    $('[data-bs-toggle="tooltip"]').each(function () {
        new bootstrap.Tooltip($(this));
    });
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

$("#btnRefresh").on('click', function () {
    $(this).blur();
    showSpinner(true);
    loadTitles(true);
});

$("#btnMetadata").on('click', function () {
    $(this).blur();
    showSpinner(true);
    $.getJSON("index.php?metadata", function (data) {
        showSpinner(false);
        $.alert({
            title: 'Metadata Update',
            content: data.msg
        })
    });
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
		renameEnabled = data.enableRename;
        romInfoEnabled = data.enableRomInfo;
    });
}

function loadTitles(forceUpdate = false) {
    var force = '';
    if (forceUpdate) {
        force = '&force';
    }
    $.getJSON("index.php?titles" + force, function (data) {
        showSpinner(false);
        titles = data.titles;
        showUnmatched(data.unmatched);
        createRows(titles);
    });
}

function confirmRename(oldName, newName) {
    $.confirm({
        columnClass: 'large',
        title: 'Confirm Rename',
        content: 'Do you want to rename <code class="small text-light bg-dark rounded p-1">' + decodeURI(oldName) + '</code> to <code class="small text-light bg-dark rounded p-1">' + newName + '</code>?',
        buttons: {
            cancel: {
                text: 'No',
                btnClass: 'btn-danger',
                action: function (btn) {
                    // nothing
                }
            },
            confirm: {
                text: 'Yes',
                btnClass: 'btn-success',
                action: function (btn) {
                    $.getJSON("index.php?rename=" + oldName, function (data) {
                        if (data.int === 0) {
                            $('button[data-path="' + oldName + '"]').parent().parent().remove();
                            if ($('#unmatchedList li').length < 1) {
                                $('#warningUnmatched').addClass('d-none');
                            }
                        } else {
                            $.alert({
                                title: 'Error',
                                content: 'There was a problem renaming the NSP, please check manually.',
                            });
                        }
                    });
                }
            }
        }
    });
}

function showUnmatched(unmatched) {
    var list = $('#unmatchedList');
    var warning = $('#warningUnmatched');
    list.empty();
    warning.addClass('d-none');
    if (unmatched.length > 0) {
        var unmatchedTemplate = $('#unmatchedTemplate').html();
        $.each(unmatched, function (i, path) {
            var row = tmpl(unmatchedTemplate, {
                name: path,
                path: encodeURI(path)
            });
            list.append(row)
        });
        warning.removeClass('d-none');
        $('.btnRename').on('click', function () {
            var path = $(this).data('path');
            var preview = '&preview';
            $.getJSON("index.php?rename=" + path + preview, function (data) {
                if (data.int === 0) {
                    confirmRename(data.old, data.new);
                }
            });
        });
    }
    console.log();
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
    init();
}

function showSpinner(show) {
    if (show) {
        $('#loadingSpinner').removeClass('d-none');
        $('#brandLogo').addClass('d-none');
    } else {
        $('#loadingSpinner').addClass('d-none');
        $('#brandLogo').removeClass('d-none');
    }
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
        $(event.target).parents('li').find('.listChevron').toggleClass("bi-chevron-down bi-chevron-up");
    });
}

function enableNetInstall() {
    $('.btnNetInstall').on('click', function () {
        $(this).blur();
        var titleId = $(this).data("title-id");
        modalNetInstall(titleId);
    });
}


function enableRomInfo() {
    $('.btnRomInfo').on('click', function () {
        var path = $(this).data('path');
        $.getJSON("index.php?rominfo=" + encodeURIComponent(path), function (data) {
            if (data.titleId) {
                modalRomInfo(path,data);
            } else {
                $.alert({
                    title: 'Error',
                    content: 'ROM Info could not be read.',
                });
            }
        });
    });
}


function enableAnalyze() {
    $('.btnAnalyze').on('click', function () {
        $(this).blur();
        $.getJSON("index.php?rename=" + encodeURIComponent($(this).data('path')) + '&preview', function (data) {
            if (data.int >= 0) {
                if (data.old === data.new) {
                    $.alert({
                        title: 'Filename is correct',
                        content: 'The current filename matches the expected filename.'
                    });
                } else {
                    confirmRename(data.old, data.new);
                }
            } else {
                alert(data.msg);
            }
        }).done(function () {
            // foo
        });
    });
}

function enablePopovers() {
    $('#titleList [data-bs-toggle="tooltip"]').each(function () {
        new bootstrap.Tooltip($(this), {
            container: $(this).parent()
        });
    });
}

function init() {
    lazyLoad();
    enableListTriggers();
    enableNetInstall();
	enableRomInfo();
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

function createCard(titleId, title) {
    var listUpdates = [];
    var listDlc = []
    var updateTemplate = $('#updateTemplate').html();
    $.each(title.updates, function (updateVersion, update) {
        listUpdates += tmpl(updateTemplate, {
            titleId: titleId.substr(0, 13) + '800',
            version: updateVersion,
            revision: updateVersion / 65536,
            date: update.date,
            url: contentUrl + '/' + update.path,
            path: encodeURI(update.path),
            size: bytesToHuman(update.size_real),
            enableRename: (renameEnabled) ? "": "d-none",
            enableRomInfo: (romInfoEnabled) ? "": "d-none"
        });
    });
    var dlcTemplate = $('#dlcTemplate').html();
    $.each(title.dlc, function (dlcId, dlc) {
        listDlc += tmpl(dlcTemplate, {
            titleId: dlcId,
            name: dlc.name,
            url: contentUrl + '/' + dlc.path,
            path: encodeURI(dlc.path),
            size: bytesToHuman(dlc.size_real),
            enableRename: (renameEnabled) ? "": "d-none",
            enableRomInfo: (romInfoEnabled) ? "": "d-none"
        });
    });
    var updateClass = 'bg-danger';
    if (title.latest_version === 0 || title.latest_version in title.updates) {
        updateClass = 'bg-success';
    }
    var countUpdates = Object.keys(title.updates).length;
    var countDlc = Object.keys(title.dlc).length;
    var cardTemplate = $('#cardTemplate');
    var card = tmpl(cardTemplate.html(), {
        titleId: titleId,
		fileType: title.fileType,
        thumbUrl: title.thumb,
        bannerUrl: title.banner,
        name: title.name,
        intro: title.intro,
        enableNetInstall: (netInstallEnabled) ? "" : "d-none",
		enableRename: (renameEnabled) ? "" : "d-none",
		enableRomInfo: (romInfoEnabled) ? "": "d-none",
        latestVersion: title.latest_version == null ? "?" : title.latest_version,
        latestDate: title.latest_date == null ? "?" : title.latest_date,
        updateClass: updateClass,
        path: encodeURI(title.path),
        url: contentUrl + '/' + title.path,
        size: bytesToHuman(title.size_real),
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

function ncacontentType(contentType){
	if(contentType == 0){
		return "Program";
	}
	if(contentType == 1){
		return "Meta";
	}
	if(contentType == 2){
		return "Control";
	}
	if(contentType == 3){
		return "Manual";
	}
	if(contentType == 4){
		return "Data";
	}
	if(contentType == 5){
		return "PublicData";
	}
}

function modalRomInfo(path,romData){
	$("#modalRomInfoBody").empty();
    var contentTemplate = $("#romInfoTemplate").html();
	var contentDlcTemplate = $("#romInfoDlcTemplate").html();
	var contentfileTemplate = $("#romInfofilesCheckTemplate").html();
	var myType = "";
	if(romData.mediaType == 128){
		myType = "Base Game";
	}else if(romData.mediaType == 129){
		myType = "Update";
	}else if(romData.mediaType == 130){
		myType = "DLC";
	}
	
	var filelisttmpt = [];
	
	for (var i = 0; i < romData.filesList.length; i++) {
		if(romData.filesList[i].name.endsWith(".nca") || romData.filesList[i].name.endsWith(".ncz")){
			filelisttmpt += tmpl(contentfileTemplate, {
				fileName: romData.filesList[i].name,
				sigcheckcolor: (romData.filesList[i].sigcheck == false) ? "bg-warning" : "bg-success",
				sigcheck: (romData.filesList[i].sigcheck == false) ? "Sig Warning" : "Sig OK",
				fileSize: bytesToHuman(romData.filesList[i].filesize),
				contentType: ncacontentType(romData.filesList[i].contentType),
				path: path
			});
		}else{
			var fileExt = romData.filesList[i].name.split('.').pop();
			filelisttmpt += tmpl(contentfileTemplate, {
				fileName: romData.filesList[i].name,
				sigcheckcolor: "d-none",
				sigcheck: "Not Checked",
				fileSize: bytesToHuman(romData.filesList[i].filesize),
				contentType: fileExt.toUpperCase(),
				path: path
			});
		}
	}
	
	if(romData.mediaType != 130){
	
	var langcombotmpl = [];
	
	var romInfoLangComboTemplate = $("#romInfoLangComboTemplate").html();
	for (var i = 0; i < romData.langs.length; i++) {
			if(romData.langs[i].present){
				langcombotmpl += tmpl(romInfoLangComboTemplate, {
					langselected: "",
					langidx: i,
					lang: langFlags[i] + " " + romData.langs[i].name
				})
		}
	}
	
	
	var romtmpl = tmpl(contentTemplate, {
		titlename: romData.langs[0].title,
		publisher: romData.langs[0].publisher,
		titleId: romData.titleId.toUpperCase(),
		humanVersion: romData.humanVersion,
		intVersion: romData.version,
		mediaType: myType + " [" + romData.fileType + "]",
		sdk: romData.sdk,
		reqsysversion: romData.reqsysversion,
		titleKey: romData.titleKey,
        showThumb: (romData.mediaType == 130) ? "d-none" : "",
		imgData: "data:image/jpeg;base64,"+romData.langs[0].gameIcon,
		xciupdatepartition: (romData.fwupdateversion == false) ? "d-none" : "",
		fwupdateversion: (romData.fwupdateversion == false) ? "none" : romData.fwupdateversion,
		isnsz: (romData.fileType != "NSZ") ? "d-none" : "",
		compressedsize: (romData.fileType != "NSZ") ? "" : bytesToHuman(romData.compressedsize),
		compressedratio: (romData.originalsize/romData.compressedsize).toFixed(0) + ":1 ("+ ((1-(romData.compressedsize/romData.originalsize))*100).toFixed(1) +"%)",
		originalsize: (romData.fileType != "NSZ") ? "" : bytesToHuman(romData.originalsize),
		path: path,
		langcombo: langcombotmpl,
		filescheck: filelisttmpt
	})
	$("#modalRomInfoBody").append(romtmpl);
	$('#modalRomInfo').modal('show');
	}else{
	var romtmpl = tmpl(contentDlcTemplate, {
		titleId: romData.titleId.toUpperCase(),
		intVersion: romData.version,
		mediaType: myType + " [" + romData.fileType + "]",
		titleKey: romData.titleKey,
        filescheck: filelisttmpt
	})
	$("#modalRomInfoBody").append(romtmpl);
	$('#modalRomInfo').modal('show');	
	}
	
	$('.btnRomFile').on('click', function () {
        var path = $(this).data('path');
		var ncaname = $(this).data('nca-name');
		window.open("index.php?romfilename=" + path + "&romfile=" + ncaname)
    });
	
	$('.btnFWDownload').on('click', function () {
        var path = $(this).data('path');
		var fwname = $(this).data('fw-name');
		window.open("index.php?xcifile=" + path + "&fwfilename=" + fwname +".tar");
    });
	
	$('#modalRomInfoBody [data-bs-toggle="tooltip"]').each(function () {
        new bootstrap.Tooltip($(this), {
            container: $(this).parent()
        });
    });
	
	$("#rominfoTitle").html(romData.langs[$("#rominfolanguage").val()].title);
	$("#rominfoPublisher").html(romData.langs[$("#rominfolanguage").val()].publisher);
	$("#rominfoIcon").attr("src","data:image/jpeg;base64,"+romData.langs[$("#rominfolanguage").val()].gameIcon);
	
	$( "#rominfolanguage").change(function() {
		$("#rominfoTitle").html(romData.langs[$( this ).val()].title);
		$("#rominfoPublisher").html(romData.langs[$( this ).val()].publisher);
		$("#rominfoIcon").attr("src","data:image/jpeg;base64,"+romData.langs[$( this ).val()].gameIcon);
	});
	
}

function modalNetInstall(titleId) {
    $("#startNetInstall").attr('disabled', true);
    $("#listNetInstall").empty();
    var contentTemplate = $('#netInstallContentTemplate').html();
    var countUpdates = Object.keys(titles[titleId].updates).length;
    var listUpdates = [];
    $.each(titles[titleId].updates, function (updateVersion, update) {
        listUpdates += tmpl(contentTemplate, {
            type: 'update',
            idx: updateVersion,
            name: 'v' + updateVersion + ' <small class="text-muted">(#' + updateVersion / 65536 + ', ' + update.date + ')</small>',
            path: encodeURI(update.path),
            size_real: bytesToHuman(update.size_real)
        });
    });

    var countDlc = Object.keys(titles[titleId].dlc).length;
    var listDlc = [];
    $.each(titles[titleId].dlc, function (dlcId, dlc) {
        listDlc += tmpl(contentTemplate, {
            type: 'dlc',
            idx: dlcId,
            name: dlc.name,
            path: encodeURI(dlc.path),
            size_real: bytesToHuman(dlc.size_real)
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
