currSelected = null;

function showQueryInput() {
	if (currSelected !== null) {
		currSelected.style.display = 'none';
	}

	var value = document.querySelector('input[name="queryType"]:checked').value;
	var contentID = value + "Content";
	var contentElement = document.getElementById(contentID);
	contentElement.style.display = 'block';
	currSelected = contentElement;
}