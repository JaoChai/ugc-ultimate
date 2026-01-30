import { createContext, useContext } from 'react';
import type { ReactNode } from 'react';

// Simplified: Light mode only
interface ThemeContextValue {
  theme: 'light';
  resolvedTheme: 'light';
  setTheme: (theme: 'light') => void;
}

const ThemeContext = createContext<ThemeContextValue | undefined>(undefined);

export function ThemeProvider({ children }: { children: ReactNode }) {
  // Always light mode
  const value: ThemeContextValue = {
    theme: 'light',
    resolvedTheme: 'light',
    setTheme: () => {}, // No-op since we only support light mode
  };

  return (
    <ThemeContext.Provider value={value}>
      {children}
    </ThemeContext.Provider>
  );
}

export function useTheme() {
  const context = useContext(ThemeContext);
  if (context === undefined) {
    throw new Error('useTheme must be used within a ThemeProvider');
  }
  return context;
}
