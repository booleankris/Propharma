<!doctype html>
<html>
  <body>
    <div
      style='background-color:#E5E5EA;color:#242424;font-family:"Helvetica Neue", "Arial Nova", "Nimbus Sans", Arial, sans-serif;font-size:16px;font-weight:400;letter-spacing:0.15008px;line-height:1.5;margin:0;padding:32px 0;min-height:100%;width:100%'
    >
      <table
        align="center"
        width="100%"
        style="margin:0 auto;max-width:600px;background-color:#FFFFFF"
        role="presentation"
        cellspacing="0"
        cellpadding="0"
        border="0"
      >
        <tbody>
          <tr style="width:100%">
            <td>
              <div style="padding:24px 24px 24px 24px">
                <a
                  href="https://walikotacup2025.myevents.id/img/walikota.png"
                  style="text-decoration:none"
                  target="_blank"
                  ><img
                    alt=""
                    src="https://walikotacup2025.myevents.id/img/walikota.png"
                    width="50"
                    style="width:50px;outline:none;border:none;text-decoration:none;vertical-align:middle;display:inline-block;max-width:100%"
                /></a>
              </div>
              <div style="font-weight:normal;padding:0px 24px 16px 24px">
                Halo {{ $team->manager_name }} - Manajer tim {{ $team->name }}
              </div>
              <div style="font-weight:normal;padding:0px 24px 16px 24px">
                Kami mengucapkan terima kasih atas pendaftaran tim {{ $team->name }}
                dalam WaliKota Cup 2025. <br> Untuk melengkapi proses
                pendaftaran, kami mengharapkan Anda segera mengisi data peserta
                tim melalui tautan berikut:
              </div>
              <div style="padding:16px 24px 24px 24px">
                <a
                  href="{{ url('/') }}/team/{{ $team->slug }}/{{ $data->code }}"
                  style="color:#FFFFFF;font-size:14px;font-weight:bold;background-color:#EA580C;display:inline-block;padding:12px 20px;text-decoration:none"
                  target="_blank"
                  >
                  <span>
                    </span
                  ><span>Data TIM</span
                  ><span
                    ><!--[if mso
                      ]><i
                        style="letter-spacing: 20px;mso-font-width:-100%"
                        hidden
                        >&nbsp;</i
                      ><!
                    [endif]--></span
                  ></a
                >
              </div>
              <div style="font-weight:normal;padding:16px 24px 16px 24px">
                Jika ada kendala atau pertanyaan, silakan segera menghubungi
                kami di [Email Kontak] atau [Nomor Telepon]. <br>Demikian informasi
                ini kami sampaikan. <br>Atas perhatian dan kerja samanya, kami
                ucapkan terima kasih.
              </div>
              <div style="padding:16px 0px 16px 0px">
                <hr
                  style="width:100%;border:none;border-top:1px solid #CCCCCC;margin:0"
                />
              </div>
              <div
                style="font-size:10px;font-weight:normal;text-align:center;padding:16px 24px 16px 24px"
              >
                Myevents.id X WaliKota Cup 2025
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </body>
</html>