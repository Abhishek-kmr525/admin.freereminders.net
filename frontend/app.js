// Build the command string for scripts/git-easy-push.sh
const $ = id => document.getElementById(id)

function quoteArg(s){
  if (!s) return ''
  // simple quoting for spaces and double quotes
  if (/\s|"/.test(s)) return '"' + s.replace(/"/g, '\\"') + '"'
  return s
}

function buildCommand(){
  const parts = ['./scripts/git-easy-push.sh']
  if ($('init').checked) parts.push('--init')
  const url = $('url').value.trim()
  const remote = $('remote').value.trim() || 'origin'
  if (url) { parts.push('-u', quoteArg(url)) }
  const branch = $('branch').value.trim() || 'main'
  if (branch) { parts.push('-b', quoteArg(branch)) }
  const msg = $('message').value.trim()
  if (msg) { parts.push('-m', quoteArg(msg)) }
  if ($('addAll').checked) parts.push('-a')
  if ($('force').checked) parts.push('--force')
  if ($('dryRun').checked) parts.push('-n')
  const paths = $('paths').value.trim()
  if (paths) {
    // split on whitespace but keep quoted groups
    const arr = paths.match(/(?:[^\s\"]+|\"[^\"]*\")+/g) || []
    for (const p of arr) parts.push(p)
  }
  return parts.join(' ')
}

function update(){
  const cmd = buildCommand()
  $('output').textContent = cmd
}

$('buildBtn').addEventListener('click', update)
$('copyBtn').addEventListener('click', async () => {
  const txt = $('output').textContent
  try {
    await navigator.clipboard.writeText(txt)
    alert('Command copied to clipboard')
  } catch (e) {
    // fallback
    const ta = document.createElement('textarea')
    ta.value = txt
    document.body.appendChild(ta)
    ta.select()
    document.execCommand('copy')
    ta.remove()
    alert('Command copied to clipboard (fallback)')
  }
})

// initial update
update()
