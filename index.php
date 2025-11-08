<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <title>School Management System</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
            .container { max-width: 900px; width: 100%; }
            .header { text-align: center; color: white; margin-bottom: 50px; }
            .header h1 { font-size: 42px; margin-bottom: 10px; }
            .header p { font-size: 18px; opacity: 0.9; }
            .login-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; }
            .login-card { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); text-align: center; }
            .login-card h2 { color: #333; margin-bottom: 15px; font-size: 24px; }
            .login-card p { color: #666; margin-bottom: 25px; font-size: 14px; }
            .login-card .icon { font-size: 48px; margin-bottom: 15px; }
            .btn { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 4px; font-weight: 600; }
            .btn:hover { background: #5568d3; }
            .btn-secondary { background: #764ba2; }
            .btn-secondary:hover { background: #663a91; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Glorious God's Family Christian School</h1>
                <p>School Management System</p>
            </div>

            <div class="login-options">
                <div class="login-card">
                    <div class="icon">👨‍💼</div>
                    <h2>Admin</h2>
                    <p>Access the admin dashboard to manage students, teachers, and classes</p>
                    <a href="admin-login.php" class="btn">Admin Login</a>
                </div>

                <div class="login-card">
                    <div class="icon">👨‍🎓</div>
                    <h2>Student / Teacher</h2>
                    <p>Login as a student or teacher to access your account</p>
                    <a href="login.php" class="btn btn-secondary">Student/Teacher Login</a>
                </div>
            </div>
        </div>
    </body>
</html>
