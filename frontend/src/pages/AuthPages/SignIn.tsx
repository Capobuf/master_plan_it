import PageMeta from "../../components/common/PageMeta";
import AuthLayout from "./AuthPageLayout";
import SignInForm from "../../components/auth/SignInForm";

export default function SignIn() {
  return (
    <>
      <PageMeta
        title="Accesso | Master Plan IT"
        description="Accedi all'applicazione Master Plan IT"
      />
      <AuthLayout>
        <SignInForm />
      </AuthLayout>
    </>
  );
}
