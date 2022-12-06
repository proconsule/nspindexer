var titles = [];
var keywordTimer;
var contentUrl;
var netInstallEnabled = false;
var renameEnabled = false;
var romInfoEnabled = false;
var zstdSupport = false;
var fileupload = false;

var langFlags = ["🇺🇸","🇬🇧","🇯🇵","🇫🇷","🇩🇪","🇪🇸","🇪🇸","🇮🇹","🇩🇪","🇨🇦","🇵🇹","🇷🇺","🇰🇷","🇨🇳","🇨🇳"];

lightbox.option({
      albumLabel: "Screenshot %1 of %2",
	  wrapAround: true
})

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

$("#btnfileUpload").on('click', function () {
    $(this).blur();
    $('#modalFileUpload').modal('show');
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
		
		var toastTemplate = $('#toastTemplete').html();
		var eleid = Math.floor(Math.random() * 50);
		var toasttmpl = tmpl(toastTemplate, {
			toastID: "toast"+eleid,
			toastHeader: "Metadata Update",
			toastIconcolor: "text-primary",
			toastIcon: "bi-cloud-arrow-down", 
			toastBody: data.msg
		});
		$("#toastContainer").append(toasttmpl);
			var toastele = document.getElementById("toast"+eleid);
			var myToast = bootstrap.Toast.getOrCreateInstance(toastele) 
			myToast.show();
			toastele.addEventListener('hidden.bs.toast', function () {
			toastele.remove(); 
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
		zstdSupport = data.zstdSupport;
		fileupload = data.fileupload;
		if(!fileupload){
			$("#btnfileUpload").prop('disabled', true);
		}
		if(data.showWarnings){
			showWarningToasts();
		}
		
    });
}

function showWarningToasts(){
	if(!romInfoEnabled){
		var toastTemplate = $('#toastTemplete').html();
			var eleid = Math.floor(Math.random() * 50);
			var toasttmpl = tmpl(toastTemplate, {
				toastID: "toast"+eleid,
				toastHeader: "Warning",
				toastIconcolor: "text-warning",
				toastIcon: "bi-exclamation-triangle-fill", 
				toastBody: "Rom Info disabled, no prod.keys supplied?"
			});
			$("#toastContainer").append(toasttmpl);
				var toastele = document.getElementById("toast"+eleid);
				var myToast = bootstrap.Toast.getOrCreateInstance(toastele) 
				myToast.show();
				toastele.addEventListener('hidden.bs.toast', function () {
				toastele.remove(); 
			})
	}
	if(!fileupload){
		var toastTemplate = $('#toastTemplete').html();
			var eleid = Math.floor(Math.random() * 50);
			var toasttmpl = tmpl(toastTemplate, {
				toastID: "toast"+eleid,
				toastHeader: "Warning",
				toastIconcolor: "text-warning",
				toastIcon: "bi-exclamation-triangle-fill", 
				toastBody: "File Upload Disabled, missing write permission on game directory"
			});
			$("#toastContainer").append(toasttmpl);
				var toastele = document.getElementById("toast"+eleid);
				var myToast = bootstrap.Toast.getOrCreateInstance(toastele) 
				myToast.show();
				toastele.addEventListener('hidden.bs.toast', function () {
				toastele.remove(); 
			})
	}
	if(!zstdSupport){
		var toastTemplate = $('#toastTemplete').html();
			var eleid = Math.floor(Math.random() * 50);
			var toasttmpl = tmpl(toastTemplate, {
				toastID: "toast"+eleid,
				toastHeader: "Warning",
				toastIconcolor: "text-warning",
				toastIcon: "bi-exclamation-triangle-fill", 
				toastBody: "NSZ Decompression disabled, php-ext-yastd extension not loaded"
			});
			$("#toastContainer").append(toasttmpl);
				var toastele = document.getElementById("toast"+eleid);
				var myToast = bootstrap.Toast.getOrCreateInstance(toastele) 
				myToast.show();
				toastele.addEventListener('hidden.bs.toast', function () {
				toastele.remove(); 
			})
	}
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
		    oldName = encodeURIComponent(oldName);
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

function enablescreenshotListTriggers() {
    $('.contentscreenshotListTrigger').on('click', function (event) {
        event.preventDefault();
        var target = $(this).siblings('.screenshotList')[0];
        $(target).slideToggle();
        $(event.target).parents('span').find('.listChevron').toggleClass("bi-chevron-down bi-chevron-up");
		$(event.target).parents('span').find('.introText').toggleClass("noWrap wordWrap");
		
		$('.lazy').Lazy({
        visibleOnly: true,
        bind: 'event',
        scrollDirection: 'vertical',
        effect: 'fadeIn',
        effectTime: 500
        });
		
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
		var me = this;
		$(this).html("<div class=\"spinner-border spinner-border-sm\" role=\"status\"><span class=\"visually-hidden\">Loading...</span></div>");
        $.getJSON("index.php?rominfo=" + encodeURIComponent(path), function (data) {
            if (data.titleId) {
                modalRomInfo(path,data);
            } else {
                $.alert({
                    title: 'Error',
                    content: 'ROM Info could not be read.',
                });
            }
			$(me).html("<i class=\"bi-file-earmark-binary\"></i>");
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
				$.alert({
					title: 'Alert!',
					content: data.msg,
				});
            }
        }).done(function () {
            // foo
        });
    });
}

function enableAdvDownload() {
	$('.btnAdvDownload').on('click', function () {
        $(this).blur();
		var mypath = $(this).data('path');
		$.confirm({
			columnClass: 'large',
			title: 'Download',
			content: 'NSZ File (compressed) or NSP File (decompressed)',
			buttons: {
				cancel: {
					text: 'Cancel',
					btnClass: 'btn-danger',
					action: function (btn) {
						// nothing
					}
				},
				nszbtn: {
					text: 'NSZ',
					btnClass: 'btn-success',
					action: function (btn) {
						window.open(contentUrl + '/' + mypath);
					}
				},
				nspbtn:{
					text: 'NSP',
					btnClass: 'btn-success',
					action: function (btn) {
						window.open("index.php?decompressNSZ="+ encodeURIComponent(mypath));
					}
				}
			}
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
	enablescreenshotListTriggers();
    enableNetInstall();
	enableRomInfo();
    enableAnalyze();
	enableAdvDownload();
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
            enableRomInfo: (romInfoEnabled) ? "": "disabled"
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
            enableRomInfo: (romInfoEnabled) ? "": "disabled"
        });
    });
    var updateClass = 'bg-danger';
    if (title.latest_version === 0 || title.latest_version in title.updates) {
        updateClass = 'bg-success';
    }
    var countUpdates = Object.keys(title.updates).length;
    var countDlc = Object.keys(title.dlc).length;
    var cardTemplate = $('#cardTemplate');
	var screenshotTemplate = $('#screenshotListTemplate').html();
	var screenshotstmpl = tmpl(screenshotTemplate, {
		galleryID: titleId,
		titleText: title.name,
		screen0: title.screenshots[0],
		screen1: title.screenshots[1],
		screen2: title.screenshots[2],
		screen3: title.screenshots[3]
	});
		
    var card = tmpl(cardTemplate.html(), {
        titleId: titleId,
		fileType: title.fileType,
        thumbUrl: title.thumb,
        bannerUrl: title.banner,
        name: title.name,
        intro: title.intro,
		screenshotList: screenshotstmpl,
        enableNetInstall: (netInstallEnabled) ? "" : "d-none",
		enableRename: (renameEnabled) ? "" : "d-none",
		enableRomInfo: (romInfoEnabled) ? "": "disabled",
        latestVersion: title.latest_version == null ? "?" : title.latest_version,
        latestDate: title.latest_date == null ? "?" : title.latest_date,
        updateClass: updateClass,
        path: encodeURI(title.path),
		simpleDownload: (title.path.endsWith("nsz") && zstdSupport) ? "d-none" : "",
		advDownload: (title.path.endsWith("nsz") && zstdSupport) ? "" : "d-none",
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
				$.alert({
					title: 'Alert!',
					content: status.msg,
				});
            }
        });
    } else {
        alert("Nothing to install");
    }
}

function showAnalyzeModal(data){
		
	$('#ncaanalyzeModal').modal('show');	
	$("#ncaanalyzeModalBody").empty();
			
	var contentTypeVar = ["Program","Meta","Control","Manual","Data","PublicData"];
	var keyrevisionVar = [ "1.0.0", "Unused", "2.0.0","3.0.1", "4.0.0", "5.0.0", "6.0.0", "6.2.0", "7.0.0", "8.1.0", "9.0.0", "9.1.0", "12.1.0"];
	var distributionTypeVar = ["System NCA", "Gamecard NCA"];
	var ncaanalyzeHeaderTemplate = $('#ncaanalyzeHeaderTemplate').html();
					
	var ncaheadertmpl = tmpl(ncaanalyzeHeaderTemplate, {
		rsa1: data.ret.rsa1,
		sigcheckrsa1: (data.ret.sigcheckrsa1 == false) ? "Warning" : "OK",
		sigcheckrsa1color: (data.ret.sigcheckrsa1 == false) ? "bg-warning" : "bg-success",
		rsa2: data.ret.rsa2,
		haversa2: (data.ret.exefs == false) ? "d-none": "",
		sigcheckrsa2: (data.ret.sigcheckrsa2 == false) ? "Warning" : "OK",
		sigcheckrsa2color: (data.ret.sigcheckrsa2 == false) ? "bg-warning" : "bg-success",
		compressedLabel: (data.ret.useBlockCompression != null) ? "(Uncompressed)" : "",
		contentSize: data.ret.contentSize ,
		contentSizeCompressed : (data.ret.useBlockCompression != null) ? data.ret.compressedSize : "",
		humancontentSizeCompressed: (data.ret.useBlockCompression != null) ? bytesToHuman(data.ret.compressedSize) : "",
		humancontentSize: bytesToHuman(data.ret.contentSize),
		isncz: (data.ret.useBlockCompression != null) ? "" : "d-none",
		useBlockCompression: (data.ret.useBlockCompression != null) ? ((data.ret.useBlockCompression == false)? "False" : "True") : "",
		sdkVersion: data.ret.sdkArray[3] +"." + data.ret.sdkArray[2] +"." + data.ret.sdkArray[1] +"." + data.ret.sdkArray[0],
		titleID: data.ret.programId,
		enctype: (data.ret.rightsId == "00000000000000000000000000000000") ? "Standard crypto":"Titlekey crypto",
		contentType: contentTypeVar[data.ret.contentType],
		distributionType: distributionTypeVar[data.ret.distributionType],
		masterkeyrevision: "0x" + data.ret.crypto_type.toString(16) + " (" + keyrevisionVar[data.ret.crypto_type] + ")",
		standardcrypto: (data.ret.rightsId == "00000000000000000000000000000000") ? "": "d-none",
		titlecrypto: (data.ret.rightsId == "00000000000000000000000000000000") ? "d-none": "",
				
		enckeyArea0: "<strong>Key 0:</strong> " +data.ret.enckeyArea[0].toUpperCase(),
		enckeyArea1: "<strong>Key 1:</strong> " +data.ret.enckeyArea[1].toUpperCase(),
		enckeyArea2: "<strong>Key 2:</strong> " +data.ret.enckeyArea[2].toUpperCase(),
		enckeyArea3: "<strong>Key 3:</strong> " +data.ret.enckeyArea[3].toUpperCase(),
		deckeyArea0: "<strong>Key 0:</strong> " +data.ret.deckeyArea[0].toUpperCase(),
		deckeyArea1: "<strong>Key 1:</strong> " +data.ret.deckeyArea[1].toUpperCase(),
		deckeyArea2: "<strong>Key 2:</strong> " +data.ret.deckeyArea[2].toUpperCase(),
		deckeyArea3: "<strong>Key 3:</strong> " +data.ret.deckeyArea[3].toUpperCase(),
				
		enctitlekey: (data.ret.enctitlekey != null)? data.ret.enctitlekey.toUpperCase() : "",
		dectitlekey: (data.ret.dectitlekey != null)? data.ret.dectitlekey.toUpperCase(): "",
				
		havesection0: (data.ret.sections[0] == false) ? "d-none" :"",
		havesection1: (data.ret.sections[1] == false) ? "d-none" :"",
		havesection2: (data.ret.sections[2] == false) ? "d-none" :"",
		havesection3: (data.ret.sections[3] == false) ? "d-none" :"",
				
		section0offset: (data.ret.sections[0] == false) ? "" : "0x" + data.ret.sections[0].offset.toString(16),
		section0size: (data.ret.sections[0] == false) ? "" : "0x" + data.ret.sections[0].size.toString(16),
		humansection0size: (data.ret.sections[0] == false) ? "" : bytesToHuman(data.ret.sections[0].size),
		section0ctr: (data.ret.sections[0] == false) ? "" : data.ret.sections[0].ctr,
		section0hash: (data.ret.sections[0] == false) ? "" : data.ret.sections[0].shahash,
		section0type: (data.ret.sections[0] == false) ? "" : data.ret.sections[0].partitionType,
	
		section1offset: (data.ret.sections[1] == false) ? "" : "0x" + data.ret.sections[1].offset.toString(16),
		section1size: (data.ret.sections[1] == false) ? "" : "0x" + data.ret.sections[1].size.toString(16),
		humansection1size: (data.ret.sections[1] == false) ? "" : bytesToHuman(data.ret.sections[1].size),
		section1ctr: (data.ret.sections[1] == false) ? "" : data.ret.sections[1].ctr,
		section1hash: (data.ret.sections[1] == false) ? "" : data.ret.sections[1].shahash,
		section1type: (data.ret.sections[1] == false) ? "" : data.ret.sections[1].partitionType,
		
		section2offset: (data.ret.sections[2] == false) ? "" : "0x" + data.ret.sections[2].offset.toString(16),
		section2size: (data.ret.sections[2] == false) ? "" : "0x" + data.ret.sections[2].size.toString(16),
		humansection2size: (data.ret.sections[2] == false) ? "" : bytesToHuman(data.ret.sections[2].size),
		section2ctr: (data.ret.sections[2] == false) ? "" : data.ret.sections[2].ctr,
		section2hash: (data.ret.sections[2] == false) ? "" : data.ret.sections[2].shahash,
		section2type: (data.ret.sections[2] == false) ? "" : data.ret.sections[2].partitionType,
				
		section3offset: (data.ret.sections[3] == false) ? "" : "0x" + data.ret.sections[3].offset.toString(16),
		section3size: (data.ret.sections[3] == false) ? "" : "0x" + data.ret.sections[3].size.toString(16),
		humansection3size: (data.ret.sections[3] == false) ? "" : bytesToHuman(data.ret.sections[3].size),
		section3ctr: (data.ret.sections[3] == false) ? "" : data.ret.sections[3].ctr,
		section3hash: (data.ret.sections[3] == false) ? "" : data.ret.sections[3].shahash,
		section3type: (data.ret.sections[3] == false) ? "" : data.ret.sections[3].partitionType,
			
				
			
	});
			
	var ncaanalyzeContentesTemplate = $("#ncaanalyzeContentesTemplate").html();
	var contentTemplate = $("#romfileContentsTemplate").html();
	var contentItemTemplate = $("#romfileContentsItemTemplate").html();
	
	var psf0filelisttmpt = [];
	var romfsfilelisttmpt = [];
	var psf0Logofilelisttmpt = [];
	
	var havepfs0 = false;
	var haveromfs = false;
	var haveromfspatch = false;
	var havepfs0Logo = false;
	
	if(data.ret.ncafilesList.pfs0){
		havepfs0= true;
		for (var i = 0; i < data.ret.ncafilesList.pfs0.length; i++) {
			psf0filelisttmpt += tmpl(contentItemTemplate, {
			fileName: data.ret.ncafilesList.pfs0[i].name,
			fileSize: bytesToHuman(data.ret.ncafilesList.pfs0[i].size),
			path: data.path,
			type: "pfs0",
			fileidx: i,
			ncaName: data.ncaName
			});
		}	
	}
	if(data.ret.ncafilesList.romfs){
		haveromfs= true;
		var treeview = new bsfiletreeview(data.path,data.ncaName,"romfs",data.ret.ncafilesList.romfs);
		romfsfilelisttmpt = treeview.out;
	}
	if(data.ret.ncafilesList.romfspatch){
		haveromfspatch = true;
	}
	
	if(data.ret.ncafilesList.pfs0Logo){
		havepfs0Logo= true;
		for (var i = 0; i < data.ret.ncafilesList.pfs0Logo.length; i++) {
			psf0Logofilelisttmpt += tmpl(contentItemTemplate, {
			fileName: data.ret.ncafilesList.pfs0Logo[i].name,
			fileSize: bytesToHuman(data.ret.ncafilesList.pfs0Logo[i].size),
			path: data.path,
			type: "pfs0Logo",
			fileidx: i,
			ncaName: data.ncaName
			});
		}	
	}

	var romtmpl = tmpl(contentTemplate, {
		pfs0fileslist: psf0filelisttmpt,
		romfsfileslist: romfsfilelisttmpt,
		pfs0Logofileslist: psf0Logofilelisttmpt,
		havepfs0: (havepfs0 == false) ? "d-none": "",
		haveromfs: (haveromfs == false) ? "d-none": "",
		haveromfspatch: (haveromfspatch == false) ? "d-none": "",
		havepfs0Logo: (havepfs0Logo == false) ? "d-none": ""
	});
			
	var ncaanalyzeContentestmpl = tmpl(ncaanalyzeContentesTemplate, {
			ncacontents: romtmpl
	});		
			
	$("#ncaanalyzeModalBody").append(ncaheadertmpl);
	$("#ncaanalyzeModalBody").append(ncaanalyzeContentestmpl);
			
			
	$('.btnncaanalyzeheadermodalChevron').on('click', function (event) {
		event.preventDefault();
		$(this).children().toggleClass("bi-chevron-up bi-chevron-down");
	});
			
	$('.btnncaanalyzecontentsmodalChevron').on('click', function (event) {
		event.preventDefault();
		$(this).children().toggleClass("bi-chevron-up bi-chevron-down");
	});
			
	$(".list-group-tree").on('click', "[data-toggle=collapse]", function(){
		$(this).toggleClass('in')
		$(this).find("i").toggleClass("bi-folder2 bi-folder2-open")
		$(this).find("i").toggleClass("text-dark text-primary")
					
		$(this).next(".list-group.collapse").collapse('toggle');
		return false;
	})
	
	$('.btnRomDownloadContents').on('click', function () {
		var path = $(this).data('path');
		var ncaname = $(this).data('nca-name');
		var type = $(this).data('type');
		var fileidx = $(this).data('fileidx');
		
		window.open("index.php?downloadfilecontents=" + encodeURIComponent(path) + "&romfile=" + ncaname + "&type=" + type +"&fileidx="+ fileidx);
	});
		
}

$("#ncaanalyzeModal").on("hidden.bs.modal", function () {
			$("#ncaanalyzeModalBody").empty();
});	


function modalRomContents(ncaData){
	$("#modalRomInfoContents").empty();
	var contentTemplate = $("#romfileContentsTemplate").html();
	var contentItemTemplate = $("#romfileContentsItemTemplate").html();
	
	
	var psf0filelisttmpt = [];
	var romfsfilelisttmpt = [];
	var psf0Logofilelisttmpt = [];
	
	var havepfs0 = false;
	var haveromfs = false;
	var haveromfspatch = false;
	var havepfs0Logo = false;
	
	if(ncaData.ret.pfs0){
		havepfs0= true;
		for (var i = 0; i < ncaData.ret.pfs0.length; i++) {
			psf0filelisttmpt += tmpl(contentItemTemplate, {
			fileName: ncaData.ret.pfs0[i].name,
			fileSize: bytesToHuman(ncaData.ret.pfs0[i].size),
			path: ncaData.path,
			type: "pfs0",
			fileidx: i,
			ncaName: ncaData.ncaName
			});
		}	
	}
	if(ncaData.ret.romfs){
		haveromfs= true;
		var treeview = new bsfiletreeview(ncaData.path,ncaData.ncaName,"romfs",ncaData.ret.romfs);
		romfsfilelisttmpt = treeview.out;
	}
	
	if(ncaData.ret.romfspatch){
		haveromfspatch= true;
	}
	
	if(ncaData.ret.pfs0Logo){
		havepfs0Logo= true;
		for (var i = 0; i < ncaData.ret.pfs0Logo.length; i++) {
			psf0Logofilelisttmpt += tmpl(contentItemTemplate, {
			fileName: ncaData.ret.pfs0Logo[i].name,
			fileSize: bytesToHuman(ncaData.ret.pfs0Logo[i].size),
			path: ncaData.path,
			type: "pfs0Logo",
			fileidx: i,
			ncaName: ncaData.ncaName
			});
		}	
	}
	
	
	
	var romtmpl = tmpl(contentTemplate, {
		pfs0fileslist: psf0filelisttmpt,
		romfsfileslist: romfsfilelisttmpt,
		pfs0Logofileslist: psf0Logofilelisttmpt,
		havepfs0: (havepfs0 == false) ? "d-none": "",
		haveromfs: (haveromfs == false) ? "d-none": "",
		haveromfspatch: (haveromfspatch == false) ? "d-none": "",
		havepfs0Logo: (havepfs0Logo == false) ? "d-none": ""
	});
	
	$("#modalRomInfoContents").append(romtmpl);
	
	$(".list-group-tree").on('click', "[data-toggle=collapse]", function(){
				$(this).toggleClass('in')
				$(this).find("i").toggleClass("bi-folder2 bi-folder2-open")
				$(this).find("i").toggleClass("text-dark text-primary")
				
				
				$(this).next(".list-group.collapse").collapse('toggle');
				return false;
	})
	
	
	$('.btnRomDownloadContents').on('click', function () {
        var path = $(this).data('path');
		var ncaname = $(this).data('nca-name');
		var type = $(this).data('type');
		var fileidx = $(this).data('fileidx');
		
		window.open("index.php?downloadfilecontents=" + encodeURIComponent(path) + "&romfile=" + ncaname + "&type=" + type +"&fileidx="+ fileidx);
    });
	
}

$("#modalRomContents").on("hidden.bs.modal", function () {
    $("#modalRomInfoContents").empty();
});


function modalRomInfo(path,romData){
	
	var ncacontentType = ["Program","Meta","Control","Manual","Data","PublicData"];
	
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
	console.log(zstdSupport);
	
	for (var i = 0; i < romData.filesList.length; i++) {
		if(romData.filesList[i].name.endsWith(".nca") || romData.filesList[i].name.endsWith(".ncz")){
			filelisttmpt += tmpl(contentfileTemplate, {
				fileName: romData.filesList[i].name,
				sigcheckcolor: (romData.filesList[i].sigcheck == false) ? "bg-warning" : "bg-success",
				sigcheck: (romData.filesList[i].sigcheck == false) ? "Sig Warning" : "Sig OK",
				fileSize: bytesToHuman(romData.filesList[i].filesize),
				contentType: ncacontentType[romData.filesList[i].contentType],
				isnca: (romData.filesList[i].name.endsWith(".ncz") == true && zstdSupport == false) ? "disabled" : "",
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
				path: path,
				isnca: "disabled"
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
		isxci: (romData.fileType != "XCI") ? "d-none" : "",
		headsigcheckcolor: (romData.fileType != "XCI")? "": (romData.headsigcheck == false) ? "bg-warning" : "bg-success",
		headsigcheck:  (romData.fileType != "XCI")? "": (romData.headsigcheck == false) ? "Warning" : "OK",
		xciromsize: (romData.fileType != "XCI") ? "" : romData.romsize,
		compressedsize: (romData.fileType != "NSZ") ? "" : bytesToHuman(romData.compressedsize),
		compressedratio: (romData.originalsize/romData.compressedsize).toFixed(1) + ":1 ("+ ((1-(romData.compressedsize/romData.originalsize))*100).toFixed(1) +"%)",
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
		window.open("index.php?romfilename=" + encodeURIComponent(path) + "&romfile=" + ncaname)
    });
	
	$('.btnRomFileContents').on('click', function () {
        var path = $(this).data('path');
		var me = this;
		var ncaname = $(this).data('nca-name');
		$(this).html("<div class=\"spinner-border spinner-border-sm\" role=\"status\"><span class=\"visually-hidden\">Loading...</span></div>");
        $.getJSON("index.php?romfilecontents=" + encodeURIComponent(path) + "&romfile=" + ncaname, function (data) {
            if (data.int >= 0) {
				modalRomContents(data);
				$('#modalRomContents').modal('show');	
			}else{
				$.alert({
					title: 'Alert!',
					content: data.msg,
				});
			}
			$(me).html("<i class=\"bi-file-code\"></i>");
			
		});

    });
	
	$('.btnFWDownload').on('click', function () {
        var path = $(this).data('path');
		var fwname = $(this).data('fw-name');
		window.open("index.php?xcifile=" + encodeURIComponent(path) + "&fwfilename=" + fwname +".tar");
    });
	
	$('.btnncaanalyzeFile').on('click', function () {
        var path = $(this).data('path');
		var ncaname = $(this).data('nca-name');
		var me = this;
		$(this).html("<div class=\"spinner-border spinner-border-sm\" role=\"status\"><span class=\"visually-hidden\">Loading...</span></div>");
        $.getJSON("index.php?ncafileanalyze=" + encodeURIComponent(path) + "&romfile=" + ncaname, function (data) {
			if (data.int >= 0) {
				showAnalyzeModal(data);
			}else{
				$.alert({
					title: 'Alert!',
					content: data.msg,
				});	
			}
			$(me).html("<i class=\"bi-calculator\"></i>");
		});
    });
	
	$('#modalRomInfoBody [data-bs-toggle="tooltip"]').each(function () {
        new bootstrap.Tooltip($(this), {
            container: $(this).parent()
        });
    });
	
	if(romData.mediaType != 130){
	$("#rominfoTitle").html(romData.langs[$("#rominfolanguage").val()].title);
	$("#rominfoPublisher").html(romData.langs[$("#rominfolanguage").val()].publisher);
	$("#rominfoIcon").attr("src","data:image/jpeg;base64,"+romData.langs[$("#rominfolanguage").val()].gameIcon);
	
	$( "#rominfolanguage").change(function() {
		$("#rominfoTitle").html(romData.langs[$( this ).val()].title);
		$("#rominfoPublisher").html(romData.langs[$( this ).val()].publisher);
		$("#rominfoIcon").attr("src","data:image/jpeg;base64,"+romData.langs[$( this ).val()].gameIcon);
	});
	}
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


/* flow.js */
var flowobj = new Flow({
	target:'fileupload.php',
	speedSmoothingFactor: 0.02
});

flowobj.on('fileSuccess', function(file,message){
	flowobj.removeFile(file);
	$("#modalFileUploadPause").prop('disabled', true);
	$("#modalFileUploadAbort").prop('disabled', true);
	$('#modalFileUploadBar').attr('class', 'progress-bar progress-bar-notransition bg-success');
});

flowobj.on('fileError', function(file, message){
	flowobj.removeFile(file);
	$("#modalFileUploadPause").prop('disabled', true);
	$("#modalFileUploadAbort").prop('disabled', true);
	$('#modalFileUploadBar').attr('class', 'progress-bar progress-bar-notransition bg-danger');
});

flowobj.on('fileProgress', function(file){
	$('#modalFileUploadSpeed').html(bytesToHuman(file.averageSpeed) + '/s ');
	$('#modalFileUploadETA').html(file.timeRemaining() + "s");
	$('#modalFileUploadBar').css({width:Math.floor(flowobj.progress()*100) + '%'}).attr('aria-valuenow', Math.floor(flowobj.progress()*100)).html(Math.floor(flowobj.progress()*100) + '%');
});

 flowobj.on('fileAdded', function(file){
	var myfilename = file.name; 
	if (myfilename.length > 45) {
		myfilename = myfilename.substring(0, 45) + "...";
	}
	$('#modalFileUploadFilename').html(myfilename);
	$('#modalFileUploadSize').html(bytesToHuman(file.size));
	$('#modalFileUploadSpeed').html("");
	$('#modalFileUploadETA').html("");
	$('#modalFileUploadBar').css({width: '0%'}).attr('aria-valuenow', 0).html('0%');
	$('#modalFileUploadBar').attr('class', 'progress-bar progress-bar-notransition bg-primary');
	$("#modalFileUploadPause").prop('disabled', false);
	$("#modalFileUploadAbort").prop('disabled', false);
 });
 
flowobj.on('filesSubmitted', function(file) {
	flowobj.upload();
});

flowobj.assignBrowse(document.getElementById('modalFileUploadChoose'),false, false, {accept: '.nsp, .nsz, .xci, .xcz'});


$("#modalFileUploadPause").on('click', function () {
	if(flowobj.isUploading()){
		flowobj.pause();
		var $el = $('#modalFileUploadPause');
		var $el2 = $('#modalFileUploadPause span:last-child');
		$el.find('span.bi').toggleClass(' bi-pause-circle-fill bi-play-circle-fill');
		$el2.text(" Resume");
	}else{
		flowobj.resume();
		var $el = $('#modalFileUploadPause');
		var $el2 = $('#modalFileUploadPause span:last-child');
		$el.find('span.bi').toggleClass('bi-play-circle-fill bi-pause-circle-fill');
		$el2.text(" Pause");
	}
});

$("#modalFileUploadAbort").on('click', function () {
	flowobj.cancel();
	$('#modalFileUploadBar').attr('class', 'progress-bar progress-bar-notransition bg-danger');
});




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
