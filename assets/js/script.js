// const now = new Date();

// const loader = document.createElement('div')
// loader.classList.add('preloader')
// loader.innerHTML = `<div class="lds-ring"><div></div><div></div><div></div><div></div></div>`
// window.start_loader = function(){
//     document.querySelectorAll('.preloader').forEach(el => { el.remove() })
//     document.body.appendChild(loader)
// }
// window.end_loader = function(){
//     document.querySelectorAll('.preloader').forEach(el => { el.remove() })
// }
// window.addEventListener("beforeunload", function(e){
//     e.preventDefault()
//     start_loader()
// })
// window.onload = function(e){
//     e.preventDefault()
//     document.getElementById('dt-year').innerHTML = now.getFullYear();
//     end_loader()
// }

document.addEventListener('DOMContentLoaded', function () {
    const changeFolderBtn = document.getElementById('changeFolderBtn');
    const folderPopup = document.getElementById('folderPopup');
    const popupOverlay = document.getElementById('popupOverlay');
    const updateFolderBtn = document.getElementById('updateFolderBtn');
    const closePopupBtn = document.getElementById('closePopupBtn');
    const selectedFolderLabel = document.getElementById('selectedFolderLabel');
    const folderIdInput = document.getElementById('folderId');
    const folderList = document.getElementById('folderList');

    // Show popup
    changeFolderBtn.addEventListener('click', function () {
        folderPopup.style.display = 'block';
        popupOverlay.style.display = 'block';
    });

    // Close popup
    closePopupBtn.addEventListener('click', function () {
        folderPopup.style.display = 'none';
        popupOverlay.style.display = 'none';
    });

    // Handle folder selection
    let clickTimeout;
    folderList.addEventListener('click', function (e) {
        const target = e.target.closest('li');
        if (!target) return;

        const subfolders = target.querySelector('.subfolders');
        const folderName = target.querySelector('.folder-name').textContent;

        // Single-click to select the folder
        clearTimeout(clickTimeout);

        // Mark the folder as selected (single-click)
        document.querySelectorAll('#folderList .folder-name').forEach(folder => {
            folder.style.color = '';
            folder.style.fontWeight = '';
        });

        target.querySelector('.folder-name').style.color = 'green';
        target.querySelector('.folder-name').style.fontWeight = 'bold';

        // Set a timeout to differentiate single-click from double-click
        clickTimeout = setTimeout(() => {
            selectedFolderLabel.textContent = folderName;
            folderIdInput.value = target.getAttribute('data-id');
        }, 300); // 300ms to distinguish single-click and double-click
    });

    // Double-click to toggle subfolders
    folderList.addEventListener('dblclick', function (e) {
        const target = e.target.closest('li');
        if (!target) return;

        const subfolders = target.querySelector('.subfolders');
        if (subfolders) {
            // Toggle display of subfolders
            subfolders.style.display = subfolders.style.display === 'block' ? 'none' : 'block';
        }
    });

    // Update folder
    updateFolderBtn.addEventListener('click', function () {
        folderPopup.style.display = 'none';
        popupOverlay.style.display = 'none';
    });
});



