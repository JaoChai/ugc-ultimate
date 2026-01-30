import { useState } from 'react';
import type { FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '@/contexts/AuthContext';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Loader2, X, Check } from 'lucide-react';

export default function Register() {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const { register, error, clearError } = useAuth();
  const navigate = useNavigate();

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setIsSubmitting(true);

    try {
      await register(name, email, password, passwordConfirmation);
      navigate('/dashboard', { replace: true });
    } catch {
      // Error handled by context
    } finally {
      setIsSubmitting(false);
    }
  }

  const features = [
    'AI-powered music generation',
    'Automatic video creation',
    'Multi-platform publishing',
  ];

  return (
    <div className="min-h-screen flex items-center justify-center bg-white px-4 py-8">
      <div className="w-full max-w-sm">
        {/* Logo */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-12 h-12 bg-slate-900 rounded-lg mb-4">
            <span className="text-white font-bold text-lg">U</span>
          </div>
          <h1 className="text-2xl font-semibold text-slate-900">UGC Ultimate</h1>
          <p className="text-slate-500 mt-1 text-sm">Start creating videos today</p>
        </div>

        <Card>
          <CardHeader className="space-y-1 pb-4">
            <CardTitle className="text-xl text-center">Create an account</CardTitle>
            <CardDescription className="text-center">
              Enter your details to get started
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4">
              {error && (
                <div className="flex items-center justify-between bg-red-50 text-red-600 px-4 py-3 rounded-lg text-sm border border-red-200">
                  <span>{error}</span>
                  <button
                    type="button"
                    onClick={clearError}
                    className="p-1 hover:bg-red-100 rounded transition-colors cursor-pointer"
                  >
                    <X className="h-4 w-4" />
                  </button>
                </div>
              )}

              <div className="space-y-2">
                <Label htmlFor="name">Full Name</Label>
                <Input
                  id="name"
                  type="text"
                  value={name}
                  onChange={(e: React.ChangeEvent<HTMLInputElement>) => setName(e.target.value)}
                  required
                  autoComplete="name"
                  placeholder="John Doe"
                  className="bg-white border-slate-200 focus:border-slate-300"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="email">Email</Label>
                <Input
                  id="email"
                  type="email"
                  value={email}
                  onChange={(e: React.ChangeEvent<HTMLInputElement>) => setEmail(e.target.value)}
                  required
                  autoComplete="email"
                  placeholder="you@example.com"
                  className="bg-white border-slate-200 focus:border-slate-300"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="password">Password</Label>
                <Input
                  id="password"
                  type="password"
                  value={password}
                  onChange={(e: React.ChangeEvent<HTMLInputElement>) => setPassword(e.target.value)}
                  required
                  autoComplete="new-password"
                  placeholder="At least 8 characters"
                  className="bg-white border-slate-200 focus:border-slate-300"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="password-confirm">Confirm Password</Label>
                <Input
                  id="password-confirm"
                  type="password"
                  value={passwordConfirmation}
                  onChange={(e: React.ChangeEvent<HTMLInputElement>) => setPasswordConfirmation(e.target.value)}
                  required
                  autoComplete="new-password"
                  placeholder="Confirm your password"
                  className="bg-white border-slate-200 focus:border-slate-300"
                />
              </div>

              <Button type="submit" className="w-full" size="lg" disabled={isSubmitting}>
                {isSubmitting ? (
                  <>
                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                    Creating account...
                  </>
                ) : (
                  'Create account'
                )}
              </Button>
            </form>

            {/* Features */}
            <div className="mt-6 pt-6 border-t border-slate-200">
              <p className="text-xs text-slate-500 mb-3 text-center">What you'll get:</p>
              <ul className="space-y-2">
                {features.map((feature) => (
                  <li key={feature} className="flex items-center gap-2 text-sm text-slate-600">
                    <Check className="h-4 w-4 text-slate-400 flex-shrink-0" />
                    {feature}
                  </li>
                ))}
              </ul>
            </div>

            <div className="mt-6 text-center">
              <p className="text-sm text-slate-500">
                Already have an account?{' '}
                <Link to="/login" className="text-slate-900 font-medium hover:underline">
                  Sign in
                </Link>
              </p>
            </div>
          </CardContent>
        </Card>

        <p className="mt-8 text-center text-xs text-slate-400">
          By creating an account, you agree to our Terms of Service and Privacy Policy
        </p>
      </div>
    </div>
  );
}
