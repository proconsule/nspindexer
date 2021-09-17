/* File Tree for ROMFS tree use 
   made for NSP indexer by proconsule and jangrewe
*/

class bsfiletreeview{
	
	constructor(filepath,ncaname,type,data) {
		this.filepath = filepath;
		this.ncaname = ncaname;
		this.type = type;
		var treedata = this.genTreeData(data);
		var out = "<div class=\"list-group list-group-tree well\">";
		out = this.dirEle(treedata[0],out);
		out += "</div>"
		this.out = out;
	}


	genTreeData(rawdata){
		let result = [];
		let level = {result};
		let idx = 0;
		rawdata.forEach(path => {
		 
			path.name.split('/').reduce((r, name, i, a) => {
				if(!r[name]) {
					r[name] = {result: []};
				r.result.push({name , size: path.size , ofs: path.ofs , fileidx: idx, nodes: r[name].result})
				}
				return r[name];
			}, level)
			idx+=1;
		}) 
		return result;
	}

	createTreeview(data){
		var treedata = genTreeData(data);
		out = "<div class=\"list-group list-group-tree well\">";
		out = dirEle(treedata[0],out);
		out += "</div>"
		return out;
	}

	dirEle(data,out){
		for (var i = 0; i < data.nodes.length; i++) {
			if(data.nodes[i].nodes.length > 0){
				out += "<a href=\"javascript:void(0);\" class=\"list-group-item\" data-toggle=\"collapse\"><i class=\"bi-folder2 text-dark\"></i> "+ data.nodes[i].name +"</a>"; 
				out += "<div class=\"list-group collapse\" >";
				out = this.dirEle(data.nodes[i],out);
			}
			if(data.nodes[i].nodes.length == 0){
				out+="<a href=\"javascript:void(0);\" class=\"list-group-item\"><i class=\"bi-file-binary-fill text-primary\"></i> " + data.nodes[i].name + "<span class=\"float-end\"> <span class=\"badge\ bg-secondary\">"+ this.bytesToHuman(data.nodes[i].size) +"</span> <button data-nca-name=\""+ this.ncaname +"\" data-path=\""+ this.filepath +"\" title=\"Download\" data-type=\""+ this.type +"\" data-fileidx=\""+ data.nodes[i].fileidx +"\" class=\"btnRomDownloadContents btn btn-sm bg-primary text-light\"><i class=\"bi-file-earmark-binary\"></i></button></span></a>";
			}
		}
		out +="</div>";
		return out;
	}

	bytesToHuman(bytes, si = false, dp = 1) {
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

}