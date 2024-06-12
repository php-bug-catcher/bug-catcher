export default function (htmlString) {
	let div = document.createElement('div');
	div.innerHTML = htmlString.trim();
	return div.firstChild;
}