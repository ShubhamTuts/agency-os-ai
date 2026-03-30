import { HashRouter } from 'react-router-dom';
import { AppProvider } from './context/AppContext';
import { AuthProvider } from './context/AuthContext';
import { NotificationProvider } from './context/NotificationContext';
import { AppRouter } from './router';
import { Toaster } from './components/ui/Toast';

export function App() {
    return (
        <HashRouter>
            <AuthProvider>
                <AppProvider>
                    <NotificationProvider>
                        <AppRouter />
                        <Toaster />
                    </NotificationProvider>
                </AppProvider>
            </AuthProvider>
        </HashRouter>
    );
}
