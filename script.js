// script.js - handles poll rendering, voting, instant results, and meme uploads

const API_VOTE = 'vote.php';
const API_UPLOAD = 'upload.php';

// Client-side "current poll" example; typically you'd fetch this from the server.
let currentPoll = {
  id: 1,
  question: "Who is the best lecturer?",
  options: [
    { id: 1, text: "Dr. Nambahu", votes: 12 },
    { id: 2, text: "Prof. Tjipuka", votes: 8 },
    { id: 3, text: "Ms. Katjivena", votes: 5 }
  ]
};

function renderPoll(poll){
  document.getElementById('poll-title').textContent = poll.question;
  const options = document.getElementById('options');
  options.innerHTML = '';
  poll.options.forEach(opt => {
    const div = document.createElement('label');
    div.className = 'option';
    div.innerHTML = `
      <input type="radio" name="option" value="${opt.id}" ${opt === poll.options[0] ? 'checked' : ''}>
      <span>${opt.text}</span>
    `;
    options.appendChild(div);
  });
  renderResults(poll);
}

function renderResults(poll){
  const results = document.getElementById('results');
  const total = poll.options.reduce((s,o) => s+ (o.votes||0), 0) || 1;
  results.innerHTML = '';
  poll.options.forEach(opt => {
    const percent = Math.round((opt.votes / total) * 100);
    const row = document.createElement('div');
    row.className = 'result-row';
    row.innerHTML = `
      <div class="result-label">${opt.text} — ${opt.votes} vote${opt.votes!==1?'s':''} (${percent}%)</div>
      <div class="result-bar"><div class="result-fill" style="width:${percent}%;"></div></div>
    `;
    results.appendChild(row);
  });
}

// Vote form
document.getElementById('poll-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const opt = document.querySelector('input[name="option"]:checked');
  if(!opt) return alert('Select an option');
  const optionId = opt.value;
  // POST vote to server
  try {
    const resp = await fetch(API_VOTE, {
      method:'POST',
      headers:{ 'Accept':'application/json', },
      body: new URLSearchParams({ poll_id: currentPoll.id, option_id: optionId })
    });
    const data = await resp.json();
    if(data.success){
      // update local poll state from server response
      currentPoll.options = data.results;
      renderResults(currentPoll);
    } else {
      alert(data.error || 'Vote failed');
    }
  } catch(err){
    console.error(err);
    alert('Network error voting');
  }
});

// Refresh results
document.getElementById('refresh-results').addEventListener('click', async () => {
  refreshResults();
});

async function refreshResults(){
  // in production, you'd GET /poll.php?poll_id=...
  // Here we simulate a fetch that returns the poll with updated vote counts.
  try {
    const resp = await fetch(API_VOTE + '?action=get&poll_id=' + encodeURIComponent(currentPoll.id));
    const data = await resp.json();
    if(data.success){
      currentPoll.options = data.results;
      renderResults(currentPoll);
    } else {
      console.warn('Could not fetch results', data);
    }
  } catch(e){
    console.error(e);
  }
}

// Meme upload
const memeForm = document.getElementById('meme-form');
memeForm.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const input = document.getElementById('meme-input');
  if(!input.files.length) return alert('Select an image');

  const form = new FormData(memeForm);
  const status = document.getElementById('upload-status');
  status.textContent = 'Uploading...';

  try {
    const resp = await fetch(API_UPLOAD, { method:'POST', body: form });
    const data = await resp.json();
    if(data.success){
      status.textContent = 'Uploaded!';
      // prepend new meme to gallery
      addMemeToGallery(data.meme);
      memeForm.reset();
      document.getElementById('meme-preview').innerHTML = '';
    } else {
      status.textContent = data.error || 'Upload failed';
    }
  } catch(err){
    console.error(err);
    status.textContent = 'Network error';
  }
});

// Preview selected meme
document.getElementById('meme-input').addEventListener('change', (e)=>{
  const preview = document.getElementById('meme-preview');
  preview.innerHTML = '';
  const file = e.target.files[0];
  if(file){
    const img = document.createElement('img');
    img.src = URL.createObjectURL(file);
    img.onload = () => URL.revokeObjectURL(img.src);
    preview.appendChild(img);
  }
});

// Gallery helpers
function addMemeToGallery(meme){
  // meme: {id, path, caption}
  const gallery = document.getElementById('meme-gallery');
  const div = document.createElement('div');
  div.className = 'meme-item';
  div.innerHTML = `<img src="${meme.path}" alt="${escapeHtml(meme.caption||'meme')}"><div class="caption">${escapeHtml(meme.caption||'')}</div>`;
  gallery.prepend(div);
}

function escapeHtml(s){
  return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

// Demo: handle local poll creation (client-only)
document.getElementById('create-poll-form').addEventListener('submit', (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  const q = fd.get('question').trim();
  if(!q) return;
  const opts = [fd.get('opt1'), fd.get('opt2'), fd.get('opt3')].filter(Boolean).map((t,i)=>({ id: i+1, text: t.trim(), votes: 0 }));
  currentPoll = { id: Date.now(), question: q, options: opts };
  renderPoll(currentPoll);
});

// Initial render
renderPoll(currentPoll);
// Optionally refresh from server on load:
refreshResults();
