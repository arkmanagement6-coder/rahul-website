const http = require('http');

const data = JSON.stringify({
  email: 'test_agent@example.com',
  password: 'TestPassword123'
});

const options = {
  hostname: 'smmpayjust.com',
  port: 80,
  path: '/api/supabase/auth/v1/signup',
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Content-Length': data.length
  }
};

const req = http.request(options, (res) => {
  console.log(`Status Code: ${res.statusCode}`);
  console.log('Headers:', res.headers);
  
  let body = '';
  res.on('data', (chunk) => {
    body += chunk;
  });
  
  res.on('end', () => {
    console.log('Body:');
    console.log(body);
  });
});

req.on('error', (e) => {
  console.error(`Problem with request: ${e.message}`);
});

req.write(data);
req.end();
