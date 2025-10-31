const students = [
  {id:'S001',name:'Alice Johnson',class:'Grade 10',gpa:3.8,attendance:96},
  {id:'S002',name:'Ben Carter',class:'Grade 11',gpa:3.2,attendance:91},
  {id:'S003',name:'Carla Gomez',class:'Grade 10',gpa:3.9,attendance:98},
  {id:'S004',name:'David Lee',class:'Grade 12',gpa:2.8,attendance:86},
  {id:'S005',name:'Eva Martin',class:'Grade 11',gpa:3.5,attendance:94},
  {id:'S006',name:'Frank Nguyen',class:'Grade 9',gpa:2.9,attendance:88},
  {id:'S007',name:'Grace Park',class:'Grade 12',gpa:3.7,attendance:95},
  {id:'S008',name:'Hector Ruiz',class:'Grade 9',gpa:3.1,attendance:90},
  {id:'S009',name:'Ivy Singh',class:'Grade 10',gpa:3.6,attendance:97},
  {id:'S010',name:'Jason Wu',class:'Grade 11',gpa:2.6,attendance:84},
  {id:'S011',name:'Kim Yoon',class:'Grade 12',gpa:3.4,attendance:92},
  {id:'S012',name:'Liam O\'Connor',class:'Grade 9',gpa:3.0,attendance:89}
];

const teachers = [
  {id:'T01',name:'Ms. Parker',subject:'Math'},
  {id:'T02',name:'Mr. Singh',subject:'Science'},
  {id:'T03',name:'Mrs. Roberts',subject:'English'},
  {id:'T04',name:'Ms. Chen',subject:'History'},
  {id:'T05',name:'Mr. Diaz',subject:'PE'}
];

function $(sel){return document.querySelector(sel)}

function computeStats(){
  const totalStudents = students.length;
  const totalTeachers = teachers.length;
  const avgGpa = (students.reduce((s,x)=>s+x.gpa,0)/totalStudents).toFixed(2);
  const attendanceRate = (students.reduce((s,x)=>s+x.attendance,0)/totalStudents).toFixed(1);

  $('#totalStudents').textContent = totalStudents;
  $('#totalTeachers').textContent = totalTeachers;
  $('#avgGpa').textContent = avgGpa;
  $('#attendanceRate').textContent = attendanceRate + '%';
}

function renderAttendanceChart(){

  const labels = [];
  for(let i=6;i>=0;i--){
    const d = new Date(); d.setDate(d.getDate()-i);
    labels.push(d.toLocaleDateString());
  }

  const mean = students.reduce((s,x)=>s+x.attendance,0)/students.length;
  const data = labels.map((_,i)=> Math.max(70, Math.round(mean + (Math.sin(i)+Math.random())*3)));

  const ctx = document.getElementById('attendanceChart').getContext('2d');
  new Chart(ctx,{
    type:'line',
    data:{labels, datasets:[{label:'Attendance %',data,color:'#3b82f6',backgroundColor:'rgba(59,130,246,0.08)',borderColor:'#3b82f6',pointRadius:3,tension:0.25}]},
    options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:false,min:60,max:100}}}
  });
}

function renderGradeChart(){
  const bins = {A:0,B:0,C:0,D:0,F:0};
  students.forEach(s=>{
    if(s.gpa>=3.5) bins.A++;
    else if(s.gpa>=3.0) bins.B++;
    else if(s.gpa>=2.5) bins.C++;
    else if(s.gpa>=2.0) bins.D++;
    else bins.F++;
  });
  const ctx = document.getElementById('gradeChart').getContext('2d');
  new Chart(ctx,{
    type:'doughnut',
    data:{labels:Object.keys(bins),datasets:[{data:Object.values(bins),backgroundColor:['#10b981','#3b82f6','#f59e0b','#ef4444','#6b7280']} ]},
    options:{responsive:true,plugins:{legend:{position:'bottom'}}}
  });
}

function renderTable(){
  const tbody = $('#studentsTable tbody');
  tbody.innerHTML = '';
  const q = ($('#globalSearch').value || '').toLowerCase();
  const pageSize = parseInt($('#pageSize').value,10) || students.length;

  const filtered = students.filter(s=>{
    if(!q) return true;
    return s.id.toLowerCase().includes(q) || s.name.toLowerCase().includes(q) || s.class.toLowerCase().includes(q);
  }).slice(0,pageSize);

  for(const s of filtered){
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${s.id}</td><td>${s.name}</td><td>${s.class}</td><td>${s.gpa.toFixed(2)}</td><td>${s.attendance}%</td>`;
    tbody.appendChild(tr);
  }
}

function exportCsv(){
  const rows = Array.from(document.querySelectorAll('#studentsTable tbody tr'));
  const header = ['ID','Name','Class','GPA','Attendance%'];
  let csv = header.join(',') + '\n';
  if(rows.length===0){

    students.forEach(s=>{
      csv += `${s.id},"${s.name}",${s.class},${s.gpa},${s.attendance}\n`;
    });
  } else {
    rows.forEach(r=>{
      const cols = Array.from(r.children).map(td=>td.textContent.replace(/\s+/g,' ').trim());
      csv += cols.map(c => (/[,\"]/.test(c) ? '"'+c.replace(/"/g,'""')+'"' : c)).join(',') + '\n';
    });
  }

  const blob = new Blob([csv],{type:'text/csv;charset=utf-8;'});
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = 'students_export.csv';
  document.body.appendChild(a); a.click(); a.remove();
  URL.revokeObjectURL(url);
}

document.addEventListener('DOMContentLoaded',()=>{
  $('#year').textContent = new Date().getFullYear();
  computeStats();
  renderAttendanceChart();
  renderGradeChart();
  renderTable();

  $('#globalSearch').addEventListener('input',()=>renderTable());
  $('#pageSize').addEventListener('change',()=>renderTable());
  $('#exportCsv').addEventListener('click',exportCsv);
});
