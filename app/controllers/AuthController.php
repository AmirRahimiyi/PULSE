
<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/Session.php';

class AuthController
{
    private User $userModel;

    public function __construct(PDO $pdo)
    {
        $this->userModel = new User($pdo);
    }

    /**
     * Register a new user
     */
    public function register(array $data): array
    {
        $username = trim($data['username'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';

        if (
            $username === '' ||
            $email === '' ||
            $password === '' ||
            $confirmPassword === ''
        ) {
            return [
                'success' => false,
                'message' => 'All fields are required.'
            ];
        }

        if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
            return [
                'success' => false,
                'message' => 'Username must contain 3 to 30 characters using letters, numbers, or underscores.'
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Please enter a valid email address.'
            ];
        }

        if (strlen($email) > 150) {
            return [
                'success' => false,
                'message' => 'Email address is too long.'
            ];
        }

        if (strlen($password) < 8) {
            return [
                'success' => false,
                'message' => 'Password must be at least 8 characters long.'
            ];
        }

        if ($password !== $confirmPassword) {
            return [
                'success' => false,
                'message' => 'Passwords do not match.'
            ];
        }

        if ($this->userModel->findByEmail($email)) {
            return [
                'success' => false,
                'message' => 'This email is already registered.'
            ];
        }

        if ($this->userModel->findByUsername($username)) {
            return [
                'success' => false,
                'message' => 'This username is already taken.'
            ];
        }

        try {
            $this->userModel->create(
                $username,
                $email,
                $password
            );

            return [
                'success' => true,
                'message' => 'Your account has been created successfully.'
            ];

        } catch (PDOException $e) {

            // Unique constraints also protect against simultaneous registrations.
            if ($e->getCode() === '23000') {
                return [
                    'success' => false,
                    'message' => 'Email or username is already registered.'
                ];
            }

            error_log($e->getMessage());

            return [
                'success' => false,
                'message' => 'Registration failed. Please try again.'
            ];
        }
    }

    /**
     * Login an existing user
     */
    public function login(array $data): array
    {
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (
            $email === '' ||
            !is_string($password) ||
            $password === ''
        ) {
            return [
                'success' => false,
                'message' => 'Email and password are required.'
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Invalid email or password.'
            ];
        }

        $user = $this->userModel->findByEmail($email);

        if (
            !$user ||
            !$this->userModel->verifyPassword(
                $password,
                $user['password']
            )
        ) {
            return [
                'success' => false,
                'message' => 'Invalid email or password.'
            ];
        }

        if (
            isset($user['status']) &&
            $user['status'] !== 'active'
        ) {
            return [
                'success' => false,
                'message' => 'Your account is currently suspended.'
            ];
        }

        Session::start();
        Session::regenerate();

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['logged_in'] = true;

        return [
            'success' => true,
            'message' => 'Login successful.'
        ];
    }

    /**
     * Logout current user
     */
    public function logout(): void
    {
        Session::destroy();
    }
}
