import React from 'react';
import AuthGuard from '@/components/AuthGuard';
import Layout from '@/components/Layout';
import ProfileComponent from '@/components/ProfileComponent';

export default function Profile() {
  return (
    <AuthGuard>
      <Layout title="ユーザー情報" showProjectsButton={false}>
        <ProfileComponent />
      </Layout>
    </AuthGuard>
  );
}
