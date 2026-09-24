const access = document.getElementById('accesso');
const dashboard = document.getElementById('dashboard');
const logout = document.getElementById('logout');

document.getElementById('login-form').addEventListener('submit', event => {
  event.preventDefault();
  const data = new FormData(event.currentTarget);
  if (data.get('email') === 'caniatoa@libero.it' && data.get('password') === 'DempPass1969') {
    document.getElementById('login-error').hidden = true;
    access.hidden = true;
    dashboard.hidden = false;
    logout.hidden = false;
    window.scrollTo({ top: 0, behavior: 'auto' });
  } else {
    document.getElementById('login-error').hidden = false;
  }
});

logout.addEventListener('click', () => {
  dashboard.hidden = true;
  access.hidden = false;
  logout.hidden = true;
  document.getElementById('login-form').reset();
  window.scrollTo({ top: 0, behavior: 'auto' });
});
document.getElementById('recover').addEventListener('click', () => {
  document.getElementById('recover-hint').hidden = false;
});
document.getElementById('settings-form').addEventListener('submit', event => {
  event.preventDefault();
  document.getElementById('settings-feedback').hidden = false;
});
document.getElementById('password-form').addEventListener('submit', event => {
  event.preventDefault();
  document.getElementById('password-feedback').hidden = false;
  event.currentTarget.reset();
});

const values = [31, 36, 28, 42, 57, 48, 68, 61, 54, 72, 66, 83, 76, 94];
const chart = document.getElementById('demo-chart');
values.forEach((value, index) => {
  const column = document.createElement('div');
  column.className = 'bar-col';
  column.title = `Giorno ${index + 1}: ${value} visite simulate`;
  const count = document.createElement('strong');
  count.textContent = value;
  const bar = document.createElement('span');
  bar.className = 'bar';
  bar.style.height = `${value}%`;
  const day = document.createElement('small');
  day.textContent = String(index + 1).padStart(2, '0');
  column.append(count, bar, day);
  chart.append(column);
});
