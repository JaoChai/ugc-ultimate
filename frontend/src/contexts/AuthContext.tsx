import { createContext, useContext, useState, useEffect } from 'react';
import type { ReactNode } from 'react';
import { api, authApi, ApiError } from '@/lib/api';
import type { User } from '@/lib/api';

interface AuthContextType {
  user: User | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  login: (email: string, password: string) => Promise<void>;
  register: (name: string, email: string, password: string, passwordConfirmation: string) => Promise<void>;
  logout: () => Promise<void>;
  error: string | null;
  clearError: () => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const token = api.getToken();
    if (token) {
      fetchUser();
    } else {
      setIsLoading(false);
    }
  }, []);

  async function fetchUser() {
    try {
      const response = await authApi.getUser();
      setUser(response.user);
    } catch (err) {
      api.setToken(null);
    } finally {
      setIsLoading(false);
    }
  }

  async function login(email: string, password: string) {
    setError(null);
    try {
      const response = await authApi.login({ email, password });
      api.setToken(response.token);
      setUser(response.user);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError('An unexpected error occurred');
      }
      throw err;
    }
  }

  async function register(
    name: string,
    email: string,
    password: string,
    passwordConfirmation: string
  ) {
    setError(null);
    try {
      const response = await authApi.register({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
      });
      api.setToken(response.token);
      setUser(response.user);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError('An unexpected error occurred');
      }
      throw err;
    }
  }

  async function logout() {
    try {
      await authApi.logout();
    } finally {
      api.setToken(null);
      setUser(null);
    }
  }

  function clearError() {
    setError(null);
  }

  return (
    <AuthContext.Provider
      value={{
        user,
        isLoading,
        isAuthenticated: !!user,
        login,
        register,
        logout,
        error,
        clearError,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
